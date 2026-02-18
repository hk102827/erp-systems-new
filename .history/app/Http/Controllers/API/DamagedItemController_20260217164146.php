<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DamagedItem;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DamagedItemController extends Controller
{
    /**
     * Get all damaged items
     */
    public function index(Request $request)
    {
        try {
            $query = DamagedItem::with([
                'product.category',
                'product.primaryImage',
                'variant',
                'branch',
                'reportedBy'
            ]);

            // Filter by branch
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by repair decision
            if ($request->has('repair_decision')) {
                $query->where('repair_decision', $request->repair_decision);
            }

            // Filter by damage type
            if ($request->has('damage_type')) {
                $query->where('damage_type', $request->damage_type);
            }

            $damaged = $query->latest('reported_date')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $damaged
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch damaged items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single damaged item
     */
    public function show($id)
    {
        try {
            $damaged = DamagedItem::with([
                'product.category',
                'product.primaryImage',
                'variant',
                'branch',
                'reportedBy'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $damaged
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Damaged item not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Report damaged item
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'branch_id' => 'required|exists:branches,id',
            'quantity' => 'required|integer|min:1',
            'damage_type' => 'required|in:Broken,Expired,Water Damage,Manufacturing Defect,Other',
            'reported_date' => 'required|date',
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
            // Check if sufficient stock exists
$query = Inventory::where('product_id', $request->product_id)
    ->where('branch_id', $request->branch_id);

if ($request->variant_id === null) {
    $query->whereNull('variant_id');
} else {
    $query->where('variant_id', $request->variant_id);
}

$inventory = $query->first();


            if (!$inventory || $inventory->available_quantity < $request->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock to report as damaged'
                ], 400);
            }

            // Create damaged item record
            $damaged = DamagedItem::create([
                'product_id' => $request->product_id,
                'variant_id' => $request->variant_id,
                'branch_id' => $request->branch_id,
                'quantity' => $request->quantity,
                'damage_type' => $request->damage_type,
                'reported_by' => auth()->id(),
                'reported_date' => $request->reported_date,
                'status' => 'Pending',
                'repair_decision' => 'Pending',
            ]);

            // Deduct from branch inventory
            $inventory->quantity -= $request->quantity;
            $inventory->available_quantity = $inventory->quantity - $inventory->reserved_quantity;
            $inventory->last_updated = now();
            $inventory->save();

            // Move to Repair Branch
            $repairBranch = Branch::where('branch_type', 'Repair')->first();
            
            if ($repairBranch) {
                $repairInventory = Inventory::firstOrCreate(
                    [
                        'product_id' => $request->product_id,
                        'variant_id' => $request->variant_id,
                        'branch_id' => $repairBranch->id,
                    ],
                    [
                        'quantity' => 0,
                        'reserved_quantity' => 0,
                        'available_quantity' => 0,
                        'reorder_point' => 0,
                    ]
                );

                $repairInventory->quantity += $request->quantity;
                $repairInventory->available_quantity = $repairInventory->quantity - $repairInventory->reserved_quantity;
                $repairInventory->last_updated = now();
                $repairInventory->save();

                // Log movement
                InventoryMovement::create([
                    'product_id' => $request->product_id,
                    'variant_id' => $request->variant_id,
                    'from_branch_id' => $request->branch_id,
                    'to_branch_id' => $repairBranch->id,
                    'movement_type' => 'Damage',
                    'quantity' => $request->quantity,
                    'reference_type' => 'DamagedItem',
                    'reference_id' => $damaged->id,
                    'notes' => "Damaged: {$request->damage_type}",
                    'moved_by' => auth()->id(),
                    'movement_date' => now(),
                ]);

                $damaged->update(['status' => 'Sent to Repair']);
            }

            DB::commit();

            $damaged->load([
                'product',
                'variant',
                'branch',
                'reportedBy'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Damaged item reported successfully',
                'data' => $damaged
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to report damaged item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Make repair decision
     */
    public function makeDecision(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'repair_decision' => 'required|in:Repairable,Not Repairable',
            'repair_notes' => 'nullable|string',
            'expense_amount' => 'required_if:repair_decision,Not Repairable|nullable|numeric|min:0',
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
            $damaged = DamagedItem::findOrFail($id);

            if ($damaged->status !== 'Sent to Repair') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only items in repair can have decisions made'
                ], 400);
            }

            $damaged->update([
                'repair_decision' => $request->repair_decision,
                'repair_notes' => $request->repair_notes,
                'expense_amount' => $request->expense_amount,
            ]);

            $repairBranch = Branch::where('branch_type', 'Repair')->first();

            if ($request->repair_decision === 'Repairable') {
                // Return to original branch
                $damaged->update(['status' => 'Repaired']);

                // Deduct from Repair Branch
                $repairInventory = Inventory::where('product_id', $damaged->product_id)
                    ->where('variant_id', $damaged->variant_id)
                    ->where('branch_id', $repairBranch->id)
                    ->first();

                if ($repairInventory) {
                    $repairInventory->quantity -= $damaged->quantity;
                    $repairInventory->available_quantity = $repairInventory->quantity - $repairInventory->reserved_quantity;
                    $repairInventory->last_updated = now();
                    $repairInventory->save();
                }

                // Add back to original branch
                $originalInventory = Inventory::where('product_id', $damaged->product_id)
                    ->where('variant_id', $damaged->variant_id)
                    ->where('branch_id', $damaged->branch_id)
                    ->first();

                if ($originalInventory) {
                    $originalInventory->quantity += $damaged->quantity;
                    $originalInventory->available_quantity = $originalInventory->quantity - $originalInventory->reserved_quantity;
                    $originalInventory->last_updated = now();
                    $originalInventory->save();
                }

                // Log movement
                InventoryMovement::create([
                    'product_id' => $damaged->product_id,
                    'variant_id' => $damaged->variant_id,
                    'from_branch_id' => $repairBranch->id,
                    'to_branch_id' => $damaged->branch_id,
                    'movement_type' => 'Repair',
                    'quantity' => $damaged->quantity,
                    'reference_type' => 'DamagedItem',
                    'reference_id' => $damaged->id,
                    'notes' => 'Repaired and returned',
                    'moved_by' => auth()->id(),
                    'movement_date' => now(),
                ]);

            } else {
                // Move to Discard Branch
                $damaged->update(['status' => 'Discarded']);

                $discardBranch = Branch::where('branch_type', 'Discard')->first();

                if ($discardBranch && $repairBranch) {
                    // Deduct from Repair Branch
                    $repairInventory = Inventory::where('product_id', $damaged->product_id)
                        ->where('variant_id', $damaged->variant_id)
                        ->where('branch_id', $repairBranch->id)
                        ->first();

                    if ($repairInventory) {
                        $repairInventory->quantity -= $damaged->quantity;
                        $repairInventory->available_quantity = $repairInventory->quantity - $repairInventory->reserved_quantity;
                        $repairInventory->last_updated = now();
                        $repairInventory->save();
                    }

                    // Add to Discard Branch
                    $discardInventory = Inventory::firstOrCreate(
                        [
                            'product_id' => $damaged->product_id,
                            'variant_id' => $damaged->variant_id,
                            'branch_id' => $discardBranch->id,
                        ],
                        [
                            'quantity' => 0,
                            'reserved_quantity' => 0,
                            'available_quantity' => 0,
                            'reorder_point' => 0,
                        ]
                    );

                    $discardInventory->quantity += $damaged->quantity;
                    $discardInventory->available_quantity = $discardInventory->quantity - $discardInventory->reserved_quantity;
                    $discardInventory->last_updated = now();
                    $discardInventory->save();

                    // Log movement
                    InventoryMovement::create([
                        'product_id' => $damaged->product_id,
                        'variant_id' => $damaged->variant_id,
                        'from_branch_id' => $repairBranch->id,
                        'to_branch_id' => $discardBranch->id,
                        'movement_type' => 'Discard',
                        'quantity' => $damaged->quantity,
                        'reference_type' => 'DamagedItem',
                        'reference_id' => $damaged->id,
                        'notes' => "Expense amount: {$request->expense_amount}",
                        'moved_by' => auth()->id(),
                        'movement_date' => now(),
                    ]);
                }
            }

            DB::commit();

            $damaged->load([
                'product',
                'variant',
                'branch',
                'reportedBy'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Repair decision made successfully',
                'data' => $damaged
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to make decision',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get damaged items statistics
     */
    public function statistics(Request $request)
    {
        try {
            $branchId = $request->get('branch_id');

            $query = DamagedItem::query();

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            $stats = [
                'total_damaged' => $query->count(),
                'pending' => (clone $query)->where('status', 'Pending')->count(),
                'sent_to_repair' => (clone $query)->where('status', 'Sent to Repair')->count(),
                'repaired' => (clone $query)->where('status', 'Repaired')->count(),
                'discarded' => (clone $query)->where('status', 'Discarded')->count(),
                'total_expense' => (clone $query)->where('repair_decision', 'Not Repairable')->sum('expense_amount'),
                'by_damage_type' => DamagedItem::select('damage_type', DB::raw('COUNT(*) as count'))
                    ->groupBy('damage_type')
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