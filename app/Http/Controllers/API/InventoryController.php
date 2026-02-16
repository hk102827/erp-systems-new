<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Get all inventory
     */
    public function index(Request $request)
    {
        try {
            $query = Inventory::with(['product.category', 'product.primaryImage', 'variant', 'branch']);

            // Filter by branch
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            // Filter by product
            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            // Filter low stock
            if ($request->get('low_stock', false)) {
                $query->whereRaw('available_quantity <= reorder_point');
            }

            // Filter out of stock
            if ($request->get('out_of_stock', false)) {
                $query->where('quantity', 0);
            }

            $perPage = $request->get('per_page', 15);
            
            if ($request->get('all', false)) {
                $inventory = $query->get();
            } else {
                $inventory = $query->paginate($perPage);
            }

            return response()->json([
                'success' => true,
                'data' => $inventory
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch inventory',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add stock to inventory
     */
    public function addStock(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'branch_id' => 'required|exists:branches,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
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
            // Get or create inventory record
            $inventory = Inventory::firstOrCreate(
                [
                    'product_id' => $request->product_id,
                    'variant_id' => $request->variant_id,
                    'branch_id' => $request->branch_id,
                ],
                [
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'available_quantity' => 0,
                    'reorder_point' => 10,
                ]
            );

            // Update quantity
            $inventory->quantity += $request->quantity;
            $inventory->available_quantity = $inventory->quantity - $inventory->reserved_quantity;
            $inventory->last_updated = now();
            $inventory->save();

            // Log movement
            InventoryMovement::create([
                'product_id' => $request->product_id,
                'variant_id' => $request->variant_id,
                'from_branch_id' => null,
                'to_branch_id' => $request->branch_id,
                'movement_type' => 'Purchase',
                'quantity' => $request->quantity,
                'notes' => $request->notes,
                'moved_by' => auth()->id(),
                'movement_date' => now(),
            ]);

            DB::commit();

            $inventory->load(['product', 'variant', 'branch']);

            return response()->json([
                'success' => true,
                'message' => 'Stock added successfully',
                'data' => $inventory
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Adjust stock (increase or decrease)
     */
    public function adjustStock(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'branch_id' => 'required|exists:branches,id',
            'adjustment_type' => 'required|in:increase,decrease',
            'quantity' => 'required|integer|min:1',
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
            $inventory = Inventory::where('product_id', $request->product_id)
                ->where('variant_id', $request->variant_id)
                ->where('branch_id', $request->branch_id)
                ->firstOrFail();

            $oldQuantity = $inventory->quantity;

            if ($request->adjustment_type === 'increase') {
                $inventory->quantity += $request->quantity;
            } else {
                if ($inventory->quantity < $request->quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock for decrease adjustment'
                    ], 400);
                }
                $inventory->quantity -= $request->quantity;
            }

            $inventory->available_quantity = $inventory->quantity - $inventory->reserved_quantity;
            $inventory->last_updated = now();
            $inventory->save();

            // Log movement
            InventoryMovement::create([
                'product_id' => $request->product_id,
                'variant_id' => $request->variant_id,
                'from_branch_id' => $request->adjustment_type === 'decrease' ? $request->branch_id : null,
                'to_branch_id' => $request->adjustment_type === 'increase' ? $request->branch_id : null,
                'movement_type' => 'Adjustment',
                'quantity' => $request->quantity,
                'notes' => $request->reason,
                'moved_by' => auth()->id(),
                'movement_date' => now(),
            ]);

            DB::commit();

            $inventory->load(['product', 'variant', 'branch']);

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'data' => [
                    'inventory' => $inventory,
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $inventory->quantity,
                    'adjustment' => $request->adjustment_type === 'increase' ? "+{$request->quantity}" : "-{$request->quantity}"
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory movements/history
     */
    public function movements(Request $request)
    {
        try {
            $query = InventoryMovement::with([
                'product',
                'variant',
                'fromBranch',
                'toBranch',
                'movedBy',
                'approvedBy'
            ]);

            // Filter by product
            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            // Filter by branch
            if ($request->has('branch_id')) {
                $query->where(function($q) use ($request) {
                    $q->where('from_branch_id', $request->branch_id)
                      ->orWhere('to_branch_id', $request->branch_id);
                });
            }

            // Filter by movement type
            if ($request->has('movement_type')) {
                $query->where('movement_type', $request->movement_type);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('movement_date', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('movement_date', '<=', $request->end_date);
            }

            $movements = $query->latest('movement_date')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $movements
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch movements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update reorder point
     */
    public function updateReorderPoint(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'branch_id' => 'required|exists:branches,id',
            'reorder_point' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $inventory = Inventory::where('product_id', $request->product_id)
                ->where('variant_id', $request->variant_id)
                ->where('branch_id', $request->branch_id)
                ->firstOrFail();

            $inventory->update(['reorder_point' => $request->reorder_point]);

            return response()->json([
                'success' => true,
                'message' => 'Reorder point updated successfully',
                'data' => $inventory
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update reorder point',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory valuation
     */
    public function valuation(Request $request)
    {
        try {
            $branchId = $request->get('branch_id');

            $query = DB::table('inventory')
                ->join('products', 'inventory.product_id', '=', 'products.id')
                ->leftJoin('product_variants', 'inventory.variant_id', '=', 'product_variants.id')
                ->leftJoin('branches', 'inventory.branch_id', '=', 'branches.id')
                ->select(
                    'inventory.branch_id',
                    'branches.branch_name',
                    DB::raw('SUM(inventory.quantity * COALESCE(product_variants.cost_price, products.cost_price)) as cost_value'),
                    DB::raw('SUM(inventory.quantity * COALESCE(product_variants.selling_price, products.selling_price)) as selling_value'),
                    DB::raw('SUM(inventory.quantity) as total_items')
                )
                ->groupBy('inventory.branch_id', 'branches.branch_name');

            if ($branchId) {
                $query->where('inventory.branch_id', $branchId);
            }

            $valuation = $query->get();

            $total = [
                'cost_value' => $valuation->sum('cost_value'),
                'selling_value' => $valuation->sum('selling_value'),
                'potential_profit' => $valuation->sum('selling_value') - $valuation->sum('cost_value'),
                'total_items' => $valuation->sum('total_items'),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'by_branch' => $valuation,
                    'total' => $total
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate valuation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}