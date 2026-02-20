<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CouponController extends Controller
{
    /**
     * Get all coupons
     */
    public function index(Request $request)
    {
        try {
            $query = Coupon::with('createdBy');

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Filter by channel
            if ($request->has('channel')) {
                $query->where('channel', $request->channel);
            }

            // Filter by validity
            if ($request->has('valid_only') && $request->valid_only) {
                $now = now()->toDateString();
                $query->where('valid_from', '<=', $now)
                      ->where('valid_until', '>=', $now)
                      ->where('is_active', true);
            }

            // Search by code or name
            if ($request->has('search')) {
                $query->where(function($q) use ($request) {
                    $q->where('coupon_code', 'like', "%{$request->search}%")
                      ->orWhere('coupon_name', 'like', "%{$request->search}%");
                });
            }

            $coupons = $query->latest()->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $coupons
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch coupons',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create coupon
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'coupon_code' => 'required|string|unique:coupons,coupon_code|max:50',
            'coupon_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:Percentage,Fixed Amount',
            'discount_value' => 'required|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:valid_from',
            'applicable_branches' => 'nullable|array',
            'applicable_branches.*' => 'exists:branches,id',
            'applicable_products' => 'nullable|array',
            'applicable_products.*' => 'exists:products,id',
            'applicable_categories' => 'nullable|array',
            'applicable_categories.*' => 'exists:categories,id',
            'is_active' => 'boolean',
            'channel' => 'required|in:All,POS,Website,Mobile App',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if user has permission (Super Admin only)
            if (auth()->user()->role->role_name !== 'Super Admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Super Admin can create coupons'
                ], 403);
            }

            $coupon = Coupon::create([
                'coupon_code' => strtoupper($request->coupon_code),
                'coupon_name' => $request->coupon_name,
                'description' => $request->description,
                'discount_type' => $request->discount_type,
                'discount_value' => $request->discount_value,
                'max_discount_amount' => $request->max_discount_amount,
                'min_purchase_amount' => $request->min_purchase_amount ?? 0,
                'usage_limit' => $request->usage_limit,
                'usage_limit_per_user' => $request->usage_limit_per_user ?? 1,
                'valid_from' => $request->valid_from,
                'valid_until' => $request->valid_until,
                'applicable_branches' => $request->applicable_branches,
                'applicable_products' => $request->applicable_products,
                'applicable_categories' => $request->applicable_categories,
                'is_active' => $request->is_active ?? true,
                'channel' => $request->channel,
                'created_by' => auth()->id(),
            ]);

            $coupon->load('createdBy');

            return response()->json([
                'success' => true,
                'message' => 'Coupon created successfully',
                'data' => $coupon
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create coupon',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get coupon details
     */
    public function show($id)
    {
        try {
            $coupon = Coupon::with(['createdBy', 'usage.sale', 'usage.user'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $coupon
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update coupon
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'coupon_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'nullable|in:Percentage,Fixed Amount',
            'discount_value' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'applicable_branches' => 'nullable|array',
            'applicable_products' => 'nullable|array',
            'applicable_categories' => 'nullable|array',
            'is_active' => 'boolean',
            'channel' => 'nullable|in:All,POS,Website,Mobile App',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if user has permission (Super Admin only)
            if (auth()->user()->role->role_name !== 'Super Admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Super Admin can update coupons'
                ], 403);
            }

            $coupon = Coupon::findOrFail($id);
            $coupon->update($request->only([
                'coupon_name',
                'description',
                'discount_type',
                'discount_value',
                'max_discount_amount',
                'min_purchase_amount',
                'usage_limit',
                'usage_limit_per_user',
                'valid_from',
                'valid_until',
                'applicable_branches',
                'applicable_products',
                'applicable_categories',
                'is_active',
                'channel',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Coupon updated successfully',
                'data' => $coupon
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update coupon',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete coupon
     */
    public function destroy($id)
    {
        try {
            // Check if user has permission (Super Admin only)
            if (auth()->user()->role->role_name !== 'Super Admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Super Admin can delete coupons'
                ], 403);
            }

            $coupon = Coupon::findOrFail($id);
            
            // Check if coupon has been used
            if ($coupon->times_used > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete coupon that has been used. Deactivate it instead.'
                ], 400);
            }

            $coupon->delete();

            return response()->json([
                'success' => true,
                'message' => 'Coupon deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete coupon',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate coupon
     */
    public function validate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'coupon_code' => 'required|string',
            'total_amount' => 'required|numeric|min:0',
            'branch_id' => 'nullable|exists:branches,id',
            'customer_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $coupon = Coupon::where('coupon_code', strtoupper($request->coupon_code))->first();

            if (!$coupon) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid coupon code'
                ], 404);
            }

            // Validate coupon
            $validation = $coupon->isValid($request->branch_id, $request->total_amount);
            
            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $validation['message']
                ], 400);
            }

            // Check usage limit per user
            if ($request->customer_id && $coupon->usage_limit_per_user) {
                $userUsage = CouponUsage::where('coupon_id', $coupon->id)
                    ->where('user_id', $request->customer_id)
                    ->count();

                if ($userUsage >= $coupon->usage_limit_per_user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Customer has already used this coupon'
                    ], 400);
                }
            }

            // Calculate discount
            $discount = $coupon->calculateDiscount($request->total_amount);

            return response()->json([
                'success' => true,
                'message' => 'Coupon is valid',
                'data' => [
                    'coupon' => $coupon,
                    'discount_amount' => $discount,
                    'final_amount' => $request->total_amount - $discount
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate coupon',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get coupon usage statistics
     */
    public function usageStatistics($id)
    {
        try {
            $coupon = Coupon::with('usage')->findOrFail($id);

            $stats = [
                'total_usage' => $coupon->times_used,
                'usage_limit' => $coupon->usage_limit,
                'remaining_usage' => $coupon->usage_limit ? ($coupon->usage_limit - $coupon->times_used) : null,
                'total_discount_given' => CouponUsage::where('coupon_id', $id)->sum('discount_applied'),
                'unique_users' => CouponUsage::where('coupon_id', $id)->distinct('user_id')->count('user_id'),
                'usage_by_date' => CouponUsage::where('coupon_id', $id)
                    ->selectRaw('DATE(used_at) as date, COUNT(*) as count, SUM(discount_applied) as total_discount')
                    ->groupBy('date')
                    ->orderBy('date', 'desc')
                    ->limit(30)
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch usage statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}