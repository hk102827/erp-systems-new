<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ReturnModel;
use App\Models\ReturnItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\CashMovement;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    /**
     * Get all returns
     */
    public function index(Request $request)
    {
        try {
            $query = ReturnModel::with([
                'sale',
                'branch',
                'processedBy',
                'items.product',
                'items.variant'
            ]);

            // Filter by branch
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by refund method
            if ($request->has('refund_method')) {
                $query->where('refund_method', $request->refund_method);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('return_date', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('return_date', '<=', $request->end_date);
            }

            // Search by return number
            if ($request->has('search')) {
                $query->where('return_number', 'like', "%{$request->search}%");
            }

            $returns = $query->latest('return_date')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $returns
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch returns',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create return
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sale_id' => 'required|exists:sales,id',
            'refund_method' => 'required|in:Cash,Card,Store Credit',
            'reason' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|exists:sale_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.condition' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $sale = Sale::with('items')->findOrFail($request->sale_id);

            // Validate sale status
            if ($sale->status === 'Cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot return items from a cancelled sale'
                ], 400);
            }

            $returnAmount = 0;

            // Validate return items and calculate return amount
            foreach ($request->items as $item) {
                $saleItem = SaleItem::findOrFail($item['sale_item_id']);

                // Check if sale item belongs to the sale
                if ($saleItem->sale_id !== $sale->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sale item does not belong to this sale'
                    ], 400);
                }

                // Check if quantity is valid
                if ($item['quantity'] > $saleItem->quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Return quantity cannot exceed sale quantity'
                    ], 400);
                }

                // Calculate refund amount for this item
                $itemRefundAmount = ($saleItem->total / $saleItem->quantity) * $item['quantity'];
                $returnAmount += $itemRefundAmount;
            }

            // Create return
            $return = ReturnModel::create([
                'sale_id' => $request->sale_id,
                'branch_id' => $sale->branch_id,
                'processed_by' => auth()->id(),
                'return_amount' => $returnAmount,
                'refund_method' => $request->refund_method,
                'reason' => $request->reason,
                'status' => 'Approved', // Auto-approve for now
                'return_date' => now(),
            ]);

            // Create return items and update inventory
            foreach ($request->items as $item) {
                $saleItem = SaleItem::findOrFail($item['sale_item_id']);
                $itemRefundAmount = ($saleItem->total / $saleItem->quantity) * $item['quantity'];

                ReturnItem::create([
                    'return_id' => $return->id,
                    'sale_item_id' => $item['sale_item_id'],
                    'product_id' => $saleItem->product_id,
                    'variant_id' => $saleItem->variant_id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $saleItem->unit_price,
                    'refund_amount' => $itemRefundAmount,
                    'condition' => $item['condition'] ?? null,
                ]);

                // Update inventory (add back the returned quantity)
                $inventory = Inventory::where('product_id', $saleItem->product_id)
                    ->where('variant_id', $saleItem->variant_id)
                    ->where('branch_id', $sale->branch_id)
                    ->first();

                if ($inventory) {
                    $inventory->increment('quantity', $item['quantity']);
                    $inventory->update(['available_quantity' => $inventory->quantity - $inventory->reserved_quantity]);
                }
            }

            // Update sale status
            $totalReturnedAmount = $sale->returns()->sum('return_amount');
            if ($totalReturnedAmount >= $sale->total_amount) {
                $sale->update(['status' => 'Refunded']);
            } else {
                $sale->update(['status' => 'Partially Refunded']);
            }

            // Record cash movement if cash refund
            if ($sale->cash_register_id && $request->refund_method === 'Cash') {
                CashMovement::create([
                    'cash_register_id' => $sale->cash_register_id,
                    'type' => 'Return',
                    'amount' => $returnAmount,
                    'reason' => 'Return: ' . $return->return_number,
                    'reference_id' => $return->id,
                    'reference_type' => 'Return',
                    'recorded_by' => auth()->id(),
                    'movement_date' => now(),
                ]);
            }

            DB::commit();

            $return->load(['sale', 'items.product', 'items.variant', 'processedBy', 'branch']);

            return response()->json([
                'success' => true,
                'message' => 'Return processed successfully',
                'data' => $return
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to process return',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get return details
     */
    public function show($id)
    {
        try {
            $return = ReturnModel::with([
                'sale.items.product',
                'sale.cashier',
                'branch',
                'processedBy',
                'items.product.images',
                'items.variant',
                'items.saleItem'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $return
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Return not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Approve return
     */
    public function approve($id)
    {
        try {
            $return = ReturnModel::findOrFail($id);

            if ($return->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending returns can be approved'
                ], 400);
            }

            $return->update(['status' => 'Approved']);

            return response()->json([
                'success' => true,
                'message' => 'Return approved successfully',
                'data' => $return
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve return',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject return
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $return = ReturnModel::with('items', 'sale')->findOrFail($id);

            if ($return->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending returns can be rejected'
                ], 400);
            }

            // Revert inventory changes
            foreach ($return->items as $item) {
                $inventory = Inventory::where('product_id', $item->product_id)
                    ->where('variant_id', $item->variant_id)
                    ->where('branch_id', $return->branch_id)
                    ->first();

                if ($inventory) {
                    $inventory->decrement('quantity', $item->quantity);
                    $inventory->update(['available_quantity' => $inventory->quantity - $inventory->reserved_quantity]);
                }
            }

            $return->update([
                'status' => 'Rejected',
                'reason' => $request->reason
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Return rejected successfully',
                'data' => $return
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject return',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get return statistics
     */
    public function statistics(Request $request)
    {
        try {
            $branchId = $request->get('branch_id');
            $startDate = $request->get('start_date', today()->format('Y-m-d'));
            $endDate = $request->get('end_date', today()->format('Y-m-d'));

            $query = ReturnModel::whereBetween('return_date', [$startDate, $endDate]);

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            $stats = [
                'total_returns' => $query->count(),
                'total_refund_amount' => $query->sum('return_amount'),
                'by_status' => ReturnModel::whereBetween('return_date', [$startDate, $endDate])
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->selectRaw('status, COUNT(*) as count, SUM(return_amount) as total')
                    ->groupBy('status')
                    ->get(),
                'by_refund_method' => ReturnModel::whereBetween('return_date', [$startDate, $endDate])
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->selectRaw('refund_method, COUNT(*) as count, SUM(return_amount) as total')
                    ->groupBy('refund_method')
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}