<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\CashRegister;
use App\Models\CashMovement;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    /**
     * Get all sales
     */
    public function index(Request $request)
    {
        try {
            $query = Sale::with(['branch', 'cashier', 'customer', 'items.product', 'items.variant']);

            // Filter by branch
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            // Filter by cashier
            if ($request->has('cashier_id')) {
                $query->where('cashier_id', $request->cashier_id);
            }

            // Filter by payment method
            if ($request->has('payment_method')) {
                $query->where('payment_method', $request->payment_method);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('sale_date', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('sale_date', '<=', $request->end_date);
            }

            // Search by sale number
            if ($request->has('search')) {
                $query->where('sale_number', 'like', "%{$request->search}%");
            }

            $sales = $query->latest('sale_date')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $sales
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sales',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create sale (Process transaction)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'cash_register_id' => 'nullable|exists:cash_registers,id',
            'customer_id' => 'nullable|exists:users,id',
            'payment_method' => 'required|in:Cash,Card,K-Net,Mobile Payment,Mixed',
            'cash_received' => 'nullable|numeric|min:0',
            'card_amount' => 'nullable|numeric|min:0',
            'card_reference' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
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
            // Calculate totals
            $subtotal = 0;
            $totalDiscount = 0;
            $totalTax = 0;

            foreach ($request->items as $item) {
                $itemSubtotal = $item['quantity'] * $item['unit_price'];
                $discountAmount = ($itemSubtotal * ($item['discount_percentage'] ?? 0)) / 100;
                $taxAmount = (($itemSubtotal - $discountAmount) * 0) / 100; // 0% tax for now

                $subtotal += $itemSubtotal;
                $totalDiscount += $discountAmount;
                $totalTax += $taxAmount;
            }

            $totalAmount = $subtotal - $totalDiscount + $totalTax;

            // Calculate change
            $changeGiven = 0;
            if ($request->payment_method === 'Cash' && $request->cash_received) {
                $changeGiven = $request->cash_received - $totalAmount;
                if ($changeGiven < 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient cash received'
                    ], 400);
                }
            }

            // Create sale
            $sale = Sale::create([
                'branch_id' => $request->branch_id,
                'cash_register_id' => $request->cash_register_id,
                'cashier_id' => auth()->id(),
                'customer_id' => $request->customer_id,
                'subtotal' => $subtotal,
                'discount_amount' => $totalDiscount,
                'tax_amount' => $totalTax,
                'total_amount' => $totalAmount,
                'payment_method' => $request->payment_method,
                'cash_received' => $request->cash_received,
                'change_given' => $changeGiven,
                'card_amount' => $request->card_amount,
                'card_reference' => $request->card_reference,
                'status' => 'Completed',
                'notes' => $request->notes,
                'sale_date' => now(),
            ]);

            // Create sale items and update inventory
            foreach ($request->items as $item) {
                $itemSubtotal = $item['quantity'] * $item['unit_price'];
                $discountAmount = ($itemSubtotal * ($item['discount_percentage'] ?? 0)) / 100;
                $taxAmount = (($itemSubtotal - $discountAmount) * 0) / 100;
                $itemTotal = $itemSubtotal - $discountAmount + $taxAmount;

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percentage' => $item['discount_percentage'] ?? 0,
                    'discount_amount' => $discountAmount,
                    'tax_percentage' => 0,
                    'tax_amount' => $taxAmount,
                    'subtotal' => $itemSubtotal,
                    'total' => $itemTotal,
                ]);

                // Update inventory
                $inventory = Inventory::where('product_id', $item['product_id'])
                    ->where('variant_id', $item['variant_id'] ?? null)
                    ->where('branch_id', $request->branch_id)
                    ->first();

                if ($inventory) {
                    $inventory->decrement('quantity', $item['quantity']);
                    $inventory->update(['available_quantity' => $inventory->quantity - $inventory->reserved_quantity]);
                }
            }

            // Record cash movement if cash register is provided
            if ($request->cash_register_id && $request->payment_method === 'Cash') {
                CashMovement::create([
                    'cash_register_id' => $request->cash_register_id,
                    'type' => 'Sale',
                    'amount' => $totalAmount,
                    'reason' => 'Sale: ' . $sale->sale_number,
                    'reference_id' => $sale->id,
                    'reference_type' => 'Sale',
                    'recorded_by' => auth()->id(),
                    'movement_date' => now(),
                ]);
            }

            DB::commit();

            $sale->load(['items.product', 'items.variant', 'cashier', 'branch']);

            return response()->json([
                'success' => true,
                'message' => 'Sale completed successfully',
                'data' => $sale
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to process sale',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sale details
     */
    public function show($id)
    {
        try {
            $sale = Sale::with([
                'branch',
                'cashRegister',
                'cashier',
                'customer',
                'items.product.images',
                'items.variant',
                'returns'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $sale
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get sales statistics
     */
    public function statistics(Request $request)
    {
        try {
            $branchId = $request->get('branch_id');
            $startDate = $request->get('start_date', today()->format('Y-m-d'));
            $endDate = $request->get('end_date', today()->format('Y-m-d'));

            $query = Sale::whereBetween('sale_date', [$startDate, $endDate]);

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            $stats = [
                'total_sales' => $query->count(),
                'total_revenue' => $query->sum('total_amount'),
                'total_discount' => $query->sum('discount_amount'),
                'by_payment_method' => Sale::whereBetween('sale_date', [$startDate, $endDate])
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total')
                    ->groupBy('payment_method')
                    ->get(),
                'by_status' => Sale::whereBetween('sale_date', [$startDate, $endDate])
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
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
}php artisan make:controller API/ReturnController