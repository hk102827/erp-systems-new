<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BranchController extends Controller
{
    /**
     * Get all branches
     */
    public function index(Request $request)
    {
        try {
            $query = Branch::query();

            // Filter by branch type
            if ($request->has('branch_type')) {
                $query->where('branch_type', $request->branch_type);
            }

            // Filter by status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Filter by temporary status
            if ($request->has('is_temporary')) {
                $query->where('is_temporary', $request->is_temporary);
            }

            // Filter by POS availability
            if ($request->has('has_pos')) {
                $query->where('has_pos', $request->has_pos);
            }

            // Filter by inventory availability
            if ($request->has('has_inventory')) {
                $query->where('has_inventory', $request->has_inventory);
            }

            // Filter by cash/bank availability
            if ($request->has('has_cash_bank')) {
                $query->where('has_cash_bank', $request->has_cash_bank);
            }

            // Search by name
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('branch_name', 'like', "%{$search}%")
                      ->orWhere('address', 'like', "%{$search}%");
                });
            }

            // Include user count if requested
            if ($request->get('with_users_count', false)) {
                $query->withCount('users');
            }

            $perPage = $request->get('per_page', 15);
            
            if ($request->get('all', false)) {
                $branches = $query->get();
            } else {
                $branches = $query->latest()->paginate($perPage);
            }

            return response()->json([
                'success' => true,
                'data' => $branches
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single branch
     */
    public function show($id)
    {
        try {
            $branch = Branch::withCount('users')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $branch
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Create new branch
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_name' => 'required|string|max:255|unique:branches,branch_name',
            'branch_type' => 'required|in:Warehouse,Retail,B2B,E-Commerce,Repair,Discard,Marketing,Expo',
            'has_pos' => 'boolean',
            'has_inventory' => 'boolean',
            'has_cash_bank' => 'boolean',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
            'is_temporary' => 'boolean',
            'opening_date' => 'nullable|date',
            'closing_date' => 'nullable|date|after:opening_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $branch = Branch::create([
                'branch_name' => $request->branch_name,
                'branch_type' => $request->branch_type,
                'has_pos' => $request->has_pos ?? false,
                'has_inventory' => $request->has_inventory ?? true,
                'has_cash_bank' => $request->has_cash_bank ?? false,
                'address' => $request->address,
                'phone' => $request->phone,
                'email' => $request->email,
                'is_active' => $request->is_active ?? true,
                'is_temporary' => $request->is_temporary ?? false,
                'opening_date' => $request->opening_date,
                'closing_date' => $request->closing_date,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch created successfully',
                'data' => $branch
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create branch',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update branch
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'branch_name' => 'required|string|max:255|unique:branches,branch_name,' . $id,
            'branch_type' => 'required|in:Warehouse,Retail,B2B,E-Commerce,Repair,Discard,Marketing,Expo',
            'has_pos' => 'boolean',
            'has_inventory' => 'boolean',
            'has_cash_bank' => 'boolean',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
            'is_temporary' => 'boolean',
            'opening_date' => 'nullable|date',
            'closing_date' => 'nullable|date|after:opening_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $branch = Branch::findOrFail($id);

            $branch->update([
                'branch_name' => $request->branch_name,
                'branch_type' => $request->branch_type,
                'has_pos' => $request->has_pos ?? $branch->has_pos,
                'has_inventory' => $request->has_inventory ?? $branch->has_inventory,
                'has_cash_bank' => $request->has_cash_bank ?? $branch->has_cash_bank,
                'address' => $request->address,
                'phone' => $request->phone,
                'email' => $request->email,
                'is_active' => $request->is_active ?? $branch->is_active,
                'is_temporary' => $request->is_temporary ?? $branch->is_temporary,
                'opening_date' => $request->opening_date,
                'closing_date' => $request->closing_date,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch updated successfully',
                'data' => $branch
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update branch',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft delete branch
     */
    public function destroy($id)
    {
        try {
            $branch = Branch::findOrFail($id);

            // Check if branch has users
            if ($branch->users()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete branch. Users are assigned to this branch.'
                ], 400);
            }

            $branch->delete();

            return response()->json([
                'success' => true,
                'message' => 'Branch deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete branch',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore soft deleted branch
     */
    public function restore($id)
    {
        try {
            $branch = Branch::withTrashed()->findOrFail($id);

            if (!$branch->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch is not deleted'
                ], 400);
            }

            $branch->restore();

            return response()->json([
                'success' => true,
                'message' => 'Branch restored successfully',
                'data' => $branch
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore branch',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get deleted branches
     */
    public function deleted()
    {
        try {
            $branches = Branch::onlyTrashed()
                ->latest('deleted_at')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $branches
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch deleted branches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change branch status (active/inactive)
     */
    public function changeStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'is_active' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $branch = Branch::findOrFail($id);

            $branch->update(['is_active' => $request->is_active]);

            return response()->json([
                'success' => true,
                'message' => 'Branch status changed successfully',
                'data' => $branch
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change branch status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get branches by type
     */
    public function getByType($type)
    {
        try {
            $branches = Branch::where('branch_type', $type)
                ->where('is_active', true)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $branches
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get warehouses only
     */
    public function getWarehouses()
    {
        try {
            $warehouses = Branch::where('branch_type', 'Warehouse')
                ->where('is_active', true)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $warehouses
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch warehouses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get retail branches only
     */
    public function getRetailBranches()
    {
        try {
            $branches = Branch::where('branch_type', 'Retail')
                ->where('is_active', true)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $branches
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch retail branches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get branches with POS
     */
    public function getBranchesWithPOS()
    {
        try {
            $branches = Branch::where('has_pos', true)
                ->where('is_active', true)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $branches
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get temporary branches (Expo)
     */
    public function getTemporaryBranches()
    {
        try {
            $branches = Branch::where('is_temporary', true)
                ->where('is_active', true)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $branches
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch temporary branches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get branch statistics
     */
    public function statistics()
    {
        try {
            $stats = [
                'total_branches' => Branch::count(),
                'active_branches' => Branch::where('is_active', true)->count(),
                'inactive_branches' => Branch::where('is_active', false)->count(),
                'deleted_branches' => Branch::onlyTrashed()->count(),
                'temporary_branches' => Branch::where('is_temporary', true)->count(),
                'branches_by_type' => Branch::selectRaw('branch_type, COUNT(*) as count')
                    ->groupBy('branch_type')
                    ->get()
                    ->map(function($item) {
                        return [
                            'type' => $item->branch_type,
                            'count' => $item->count
                        ];
                    }),
                'branches_with_pos' => Branch::where('has_pos', true)->count(),
                'branches_with_inventory' => Branch::where('has_inventory', true)->count(),
                'branches_with_cash_bank' => Branch::where('has_cash_bank', true)->count(),
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

    /**
     * Get branch types (for dropdown)
     */
    public function getBranchTypes()
    {
        try {
            $types = [
                'Warehouse',
                'Retail',
                'B2B',
                'E-Commerce',
                'Repair',
                'Discard',
                'Marketing',
                'Expo'
            ];

            return response()->json([
                'success' => true,
                'data' => $types
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branch types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get branch users
     */
    public function getBranchUsers($id)
    {
        try {
            $branch = Branch::findOrFail($id);
            $users = $branch->users()
                ->with('role')
                ->where('is_active', true)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'branch' => $branch,
                    'users' => $users,
                    'users_count' => $users->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branch users',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}