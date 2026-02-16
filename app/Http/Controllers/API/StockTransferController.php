<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    /**
     * Get all stock transfers
     */
    public function index(Request $request)
    {
        try {
            $query = StockTransfer::with([
                'fromBranch',
                'toBranch',
                'requestedBy',
                'approvedBy',
                'items.product',
                'items.variant'
            ]);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by from branch
            if ($request->has('from_branch_id')) {
                $query->where('from_branch_id', $request->from_branch_id);
            }

            // Filter by to branch
            if ($request->has('to_branch_id')) {
                $query->where('to_branch_id', $request->to_branch_id);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            $transfers = $query->latest()->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $transfers
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transfers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single transfer
     */
    public function show($id)
    {
        try {
            $transfer = StockTransfer::with([
                'fromBranch',
                'toBranch',
                'requestedBy',
                'approvedBy',
                'items.product.category',
                'items.product.primaryImage',
                'items.variant'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $transfer
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Create transfer request
     */
    // public function store(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'from_branch_id' => 'required|exists:branches,id',
    //         'to_branch_id' => 'required|exists:branches,id|different:from_branch_id',
    //         'transfer_type' => 'required|in:Branch-to-Branch,Warehouse-to-Branch,Branch-to-Warehouse',
    //         'notes' => 'nullable|string',
    //         'items' => 'required|array|min:1',
    //         'items.*.product_id' => 'required|exists:products,id',
    //         'items.*.variant_id' => 'nullable|exists:product_variants,id',
    //         'items.*.requested_quantity' => 'required|integer|min:1',
    //         'items.*.notes' => 'nullable|string',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Validation error',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     DB::beginTransaction();

    //     try {
    //         // Create transfer
    //         $transfer = StockTransfer::create([
    //             'from_branch_id' => $request->from_branch_id,
    //             'to_branch_id' => $request->to_branch_id,
    //             'transfer_type' => $request->transfer_type,
    //             'status' => 'Pending',
    //             'requested_by' => auth()->id(),
    //             'notes' => $request->notes,
    //         ]);

    //         // Create transfer items
    //         foreach ($request->items as $item) {
    //             StockTransferItem::create([
    //                 'transfer_id' => $transfer->id,
    //                 'product_id' => $item['product_id'],
    //                 'variant_id' => $item['variant_id'] ?? null,
    //                 'requested_quantity' => $item['requested_quantity'],
    //                 'notes' => $item['notes'] ?? null,
    //             ]);
    //         }

    //         DB::commit();

    //         $transfer->load([
    //             'fromBranch',
    //             'toBranch',
    //             'requestedBy',
    //             'items.product',
    //             'items.variant'
    //         ]);

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Transfer request created successfully',
    //             'data' => $transfer
    //         ], 201);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
            
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to create transfer request',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'from_branch_id' => 'required|exists:branches,id',
        'to_branch_id' => 'required|exists:branches,id|different:from_branch_id',
        'transfer_type' => 'required|in:Branch-to-Branch,Warehouse-to-Branch,Branch-to-Warehouse',
        'notes' => 'nullable|string',
        'items' => 'required|array|min:1',
        'items.*.product_id' => 'required|exists:products,id',
        'items.*.variant_id' => 'nullable|exists:product_variants,id',
        'items.*.quantity' => 'required|integer|min:1',
        'items.*.notes' => 'nullable|string',
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
        // Create transfer record
        $transfer = StockTransfer::create([
            'from_branch_id' => $request->from_branch_id,
            'to_branch_id' => $request->to_branch_id,
            'transfer_type' => $request->transfer_type,
            'status' => 'Completed',
            'requested_by' => auth()->id(),
            'approved_by' => auth()->id(),
            'notes' => $request->notes,
            'transfer_date' => now(),
            'received_date' => now(),
        ]);

        // Process each item
        foreach ($request->items as $itemData) {
            $quantity = $itemData['quantity'];

            // Check stock availability
            $fromInventory = Inventory::where('product_id', $itemData['product_id'])
                ->where('variant_id', $itemData['variant_id'] ?? null)
                ->where('branch_id', $request->from_branch_id)
                ->first();

            if (!$fromInventory || $fromInventory->available_quantity < $quantity) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient stock for product ID {$itemData['product_id']}. Available: " . ($fromInventory->available_quantity ?? 0) . ", Requested: {$quantity}"
                ], 400);
            }

            // Create transfer item record
            StockTransferItem::create([
                'transfer_id' => $transfer->id,
                'product_id' => $itemData['product_id'],
                'variant_id' => $itemData['variant_id'] ?? null,
                'requested_quantity' => $quantity,
                'approved_quantity' => $quantity,
                'received_quantity' => $quantity,
                'notes' => $itemData['notes'] ?? null,
            ]);

            // Deduct from source branch
            $fromInventory->quantity -= $quantity;
            $fromInventory->available_quantity = $fromInventory->quantity - $fromInventory->reserved_quantity;
            $fromInventory->last_updated = now();
            $fromInventory->save();

            // Add to destination branch
            $toInventory = Inventory::firstOrCreate(
                [
                    'product_id' => $itemData['product_id'],
                    'variant_id' => $itemData['variant_id'] ?? null,
                    'branch_id' => $request->to_branch_id,
                ],
                [
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'available_quantity' => 0,
                    'reorder_point' => 10,
                ]
            );

            $toInventory->quantity += $quantity;
            $toInventory->available_quantity = $toInventory->quantity - $toInventory->reserved_quantity;
            $toInventory->last_updated = now();
            $toInventory->save();

            // Log inventory movement
            InventoryMovement::create([
                'product_id' => $itemData['product_id'],
                'variant_id' => $itemData['variant_id'] ?? null,
                'from_branch_id' => $request->from_branch_id,
                'to_branch_id' => $request->to_branch_id,
                'movement_type' => 'Transfer',
                'quantity' => $quantity,
                'reference_type' => 'StockTransfer',
                'reference_id' => $transfer->id,
                'moved_by' => auth()->id(),
                'approved_by' => auth()->id(),
                'movement_date' => now(),
            ]);
        }

        DB::commit();

        $transfer->load([
            'fromBranch',
            'toBranch',
            'requestedBy',
            'approvedBy',
            'items.product',
            'items.variant'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Stock transferred successfully',
            'data' => $transfer
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to transfer stock',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Approve transfer
     */
    public function approve(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:stock_transfer_items,id',
            'items.*.approved_quantity' => 'required|integer|min:0',
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
            $transfer = StockTransfer::findOrFail($id);

            if ($transfer->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending transfers can be approved'
                ], 400);
            }

            // Check stock availability for each item
            foreach ($request->items as $itemData) {
                $item = StockTransferItem::findOrFail($itemData['id']);
                
                $inventory = Inventory::where('product_id', $item->product_id)
                    ->where('variant_id', $item->variant_id)
                    ->where('branch_id', $transfer->from_branch_id)
                    ->first();

                if (!$inventory || $inventory->available_quantity < $itemData['approved_quantity']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for product ID {$item->product_id}"
                    ], 400);
                }
            }

            // Update transfer items with approved quantities
            foreach ($request->items as $itemData) {
                $item = StockTransferItem::findOrFail($itemData['id']);
                $item->update(['approved_quantity' => $itemData['approved_quantity']]);
            }

            // Update transfer status
            $transfer->update([
                'status' => 'Approved',
                'approved_by' => auth()->id(),
                'transfer_date' => now(),
            ]);

            DB::commit();

            $transfer->load([
                'fromBranch',
                'toBranch',
                'requestedBy',
                'approvedBy',
                'items.product',
                'items.variant'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transfer approved successfully',
                'data' => $transfer
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete transfer (receive items)
     */
    public function complete(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:stock_transfer_items,id',
            'items.*.received_quantity' => 'required|integer|min:0',
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
            $transfer = StockTransfer::findOrFail($id);

            if ($transfer->status !== 'Approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved transfers can be completed'
                ], 400);
            }

            foreach ($request->items as $itemData) {
                $item = StockTransferItem::findOrFail($itemData['id']);
                $receivedQty = $itemData['received_quantity'];

                // Update item
                $item->update(['received_quantity' => $receivedQty]);

                if ($receivedQty > 0) {
                    // Deduct from source branch
                    $fromInventory = Inventory::where('product_id', $item->product_id)
                        ->where('variant_id', $item->variant_id)
                        ->where('branch_id', $transfer->from_branch_id)
                        ->firstOrFail();

                    $fromInventory->quantity -= $receivedQty;
                    $fromInventory->available_quantity = $fromInventory->quantity - $fromInventory->reserved_quantity;
                    $fromInventory->last_updated = now();
                    $fromInventory->save();

                    // Add to destination branch
                    $toInventory = Inventory::firstOrCreate(
                        [
                            'product_id' => $item->product_id,
                            'variant_id' => $item->variant_id,
                            'branch_id' => $transfer->to_branch_id,
                        ],
                        [
                            'quantity' => 0,
                            'reserved_quantity' => 0,
                            'available_quantity' => 0,
                            'reorder_point' => 10,
                        ]
                    );

                    $toInventory->quantity += $receivedQty;
                    $toInventory->available_quantity = $toInventory->quantity - $toInventory->reserved_quantity;
                    $toInventory->last_updated = now();
                    $toInventory->save();

                    // Log movement
                    InventoryMovement::create([
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'from_branch_id' => $transfer->from_branch_id,
                        'to_branch_id' => $transfer->to_branch_id,
                        'movement_type' => 'Transfer',
                        'quantity' => $receivedQty,
                        'reference_type' => 'StockTransfer',
                        'reference_id' => $transfer->id,
                        'moved_by' => auth()->id(),
                        'approved_by' => $transfer->approved_by,
                        'movement_date' => now(),
                    ]);
                }
            }

            // Update transfer status
            $transfer->update([
                'status' => 'Completed',
                'received_date' => now(),
            ]);

            DB::commit();

            $transfer->load([
                'fromBranch',
                'toBranch',
                'requestedBy',
                'approvedBy',
                'items.product',
                'items.variant'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transfer completed successfully',
                'data' => $transfer
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject transfer
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $transfer = StockTransfer::findOrFail($id);

            if ($transfer->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending transfers can be rejected'
                ], 400);
            }

            $transfer->update([
                'status' => 'Rejected',
                'rejection_reason' => $request->rejection_reason,
                'approved_by' => auth()->id(),
            ]);

            $transfer->load([
                'fromBranch',
                'toBranch',
                'requestedBy',
                'approvedBy',
                'items.product',
                'items.variant'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transfer rejected successfully',
                'data' => $transfer
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel transfer
     */
    public function cancel($id)
    {
        try {
            $transfer = StockTransfer::findOrFail($id);

            if (!in_array($transfer->status, ['Pending', 'Approved'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending or approved transfers can be cancelled'
                ], 400);
            }

            // Check if user is the one who requested
            if ($transfer->requested_by !== auth()->id() && !auth()->user()->isSuperAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only cancel your own transfer requests'
                ], 403);
            }

            $transfer->update(['status' => 'Cancelled']);

            return response()->json([
                'success' => true,
                'message' => 'Transfer cancelled successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transfer statistics
     */
    public function statistics(Request $request)
    {
        try {
            $branchId = $request->get('branch_id');

            $query = StockTransfer::query();

            if ($branchId) {
                $query->where(function($q) use ($branchId) {
                    $q->where('from_branch_id', $branchId)
                      ->orWhere('to_branch_id', $branchId);
                });
            }

            $stats = [
                'total_transfers' => $query->count(),
                'pending' => (clone $query)->where('status', 'Pending')->count(),
                'approved' => (clone $query)->where('status', 'Approved')->count(),
                'completed' => (clone $query)->where('status', 'Completed')->count(),
                'rejected' => (clone $query)->where('status', 'Rejected')->count(),
                'cancelled' => (clone $query)->where('status', 'Cancelled')->count(),
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