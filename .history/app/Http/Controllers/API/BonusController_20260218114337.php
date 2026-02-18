<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Bonus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BonusController extends Controller
{
    /**
     * Get all bonuses
     */
    public function index(Request $request)
    {
        try {
            $query = Bonus::with(['user', 'approvedBy']);

            // Filter by user
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by bonus type
            if ($request->has('bonus_type')) {
                $query->where('bonus_type', $request->bonus_type);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('bonus_date', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('bonus_date', '<=', $request->end_date);
            }

            $bonuses = $query->latest('bonus_date')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $bonuses
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bonuses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create bonus
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'bonus_type' => 'required|in:Newborn Child,Monthly Sales,Quarterly,Semi Annual,Annual',
            'amount' => 'required|numeric|min:0',
            'bonus_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Auto-set amount for Newborn Child bonus
            if ($request->bonus_type === 'Newborn Child') {
                $request->merge(['amount' => 20.000]);
            }

            $bonus = Bonus::create([
                'user_id' => $request->user_id,
                'bonus_type' => $request->bonus_type,
                'amount' => $request->amount,
                'bonus_date' => $request->bonus_date,
                'description' => $request->description,
                'approved_by' => auth()->id(),
            ]);

            $bonus->load(['user', 'approvedBy']);

            return response()->json([
                'success' => true,
                'message' => 'Bonus created successfully',
                'data' => $bonus
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bonus',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update bonus
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'bonus_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $bonus = Bonus::findOrFail($id);
            $bonus->update($request->all());

            $bonus->load(['user', 'approvedBy']);

            return response()->json([
                'success' => true,
                'message' => 'Bonus updated successfully',
                'data' => $bonus
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bonus',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete bonus
     */
    public function destroy($id)
    {
        try {
            $bonus = Bonus::findOrFail($id);
            $bonus->delete();

            return response()->json([
                'success' => true,
                'message' => 'Bonus deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bonus',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bonuses summary for a user
     */
    public function getUserSummary($userId, Request $request)
    {
        try {
            $year = $request->get('year', now()->format('Y'));

            $bonuses = Bonus::where('user_id', $userId)
                ->whereYear('bonus_date', $year)
                ->get();

            $summary = [
                'year' => $year,
                'total_bonuses' => $bonuses->count(),
                'total_amount' => $bonuses->sum('amount'),
                'by_type' => $bonuses->groupBy('bonus_type')->map(function($items) {
                    return [
                        'count' => $items->count(),
                        'total' => $items->sum('amount'),
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}