<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\CashRegister;
use App\Models\CashMovement;
use App\Models\Inventory;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\DiscountAuthorizationLog;
use App\Models\User;
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
            $query = Sale::with([
                'branch',
                'cashier',
                'salesStaff',
                'customer',
                'items.product',
                'items.variant'
            ]);

            // Filter by branch
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            // Filter by cashier
            if ($request->has('cashier_id')) {
                $query->where('cashier_id', $request->cashier_id);
            }

            // Filter by sales staff
            if ($request->has('sales_staff_id')) {
                $query->where('sales_staff_id', $request->sales_staff_id);
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

            // Filter gift purchases
            if ($request->has('is_gift')) {
                $query->where('is_gift', $request->is_gift);
            }

            // Filter employee purchases
            if ($request->has('is_employee_purchase')) {
                $query->where('is_employee_purchase', $request->is_employee_purchase);
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
            'sales_staff_id' => 'nullable|exists:users,id',
            'customer_id' => 'nullable|exists:users,id',
            'payment_method' => 'required|in:Cash,Card,K-Net,Mobile Payment,Mixed',
            'cash_received' => 'nullable|numeric|min:0',
            'card_amount' => 'nullable|numeric|min:0',
            'card_reference' => 'nullable|string',
            'coupon_code' => 'nullable|string',
            'is_gift' => 'boolean',
            'is_employee_purchase' => 'boolean',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.requires_authorization' => 'nullable|boolean',
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
            $user = auth()->user();

            // Check discount authorization
            foreach ($request->items as $item) {
                if (isset($item['discount_percentage']) && $item['discount_percentage'] > 0) {
                    $authorized = $this->checkDiscountAuthorization($user, $item['discount_percentage']);
                    
                    if (!$authorized['allowed']) {
                        return response()->json([
                            'success' => false,
                            'message' => $authorized['message'],
                            'requires_authorization' => true,
                            'max_allowed_discount' => $authorized['max_discount']
                        ], 403);
                    }
                }
            }

            // Calculate totals
            $subtotal = 0;
            $totalDiscount = 0;
            $totalTax = 0;

            foreach ($request->items as $item) {
                $itemSubtotal = $item['quantity'] * $item['unit_price'];
                $discountAmount = ($itemSubtotal * ($item['discount_percentage'] ?? 0)) / 100;
                $taxAmount = (($itemSubtotal - $discountAmount) * 0) / 100;

                $subtotal += $itemSubtotal;
                $totalDiscount += $discountAmount;
                $totalTax += $taxAmount;
            }

            // Apply employee discount if applicable
            $employeeDiscountAmount = 0;
            if ($request->is_employee_purchase) {
                $employeeDiscountAmount = $this->calculateEmployeeDiscount($subtotal - $totalDiscount);
            }

            $totalAmount = $subtotal - $totalDiscount - $employeeDiscountAmount + $totalTax;

            // Apply coupon if provided
            $couponDiscount = 0;
            $couponCode = null;
            if ($request->coupon_code) {
                $couponResult = $this->applyCoupon($request->coupon_code, $totalAmount, $request->branch_id, $request->customer_id);
                
                if (!$couponResult['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => $couponResult['message']
                    ], 400);
                }

                $couponDiscount = $couponResult['discount'];
                $couponCode = $request->coupon_code;
                $totalAmount -= $couponDiscount;
            }

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
                'sales_staff_id' => $request->sales_staff_id,
                'customer_id' => $request->customer_id,
                'subtotal' => $subtotal,
                'discount_amount' => $totalDiscount,
                'tax_amount' => $totalTax,
                'total_amount' => $totalAmount,
                'coupon_discount' => $couponDiscount,
                'coupon_code' => $couponCode,
                'payment_method' => $request->payment_method,
                'cash_received' => $request->cash_received,
                'change_given' => $changeGiven,
                'card_amount' => $request->card_amount,
                'card_reference' => $request->card_reference,
                'is_gift' => $request->is_gift ?? false,
                'is_employee_purchase' => $request->is_employee_purchase ?? false,
                'employee_discount_amount' => $employeeDiscountAmount,
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

                // Log discount authorization if needed
                if (isset($item['discount_percentage']) && $item['discount_percentage'] > 0) {
                    DiscountAuthorizationLog::create([
                        'sale_id' => $sale->id,
                        'requested_by' => auth()->id(),
                        'authorized_by' => auth()->id(),
                        'discount_percentage' => $item['discount_percentage'],
                        'discount_amount' => $discountAmount,
                        'status' => 'Approved',
                        'reason' => 'Authorized by ' . $user->name,
                    ]);
                }

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

            // Record coupon usage
            if ($couponCode && $couponDiscount > 0) {
                $coupon = Coupon::where('coupon_code', $couponCode)->first();
                if ($coupon) {
                    CouponUsage::create([
                        'coupon_id' => $coupon->id,
                        'sale_id' => $sale->id,
                        'user_id' => $request->customer_id,
                        'discount_applied' => $couponDiscount,
                        'used_at' => now(),
                    ]);

                    $coupon->increment('times_used');
                }
            }

            // Record cash movement if cash register is provided
            if ($request->cash_register_id && in_array($request->payment_method, ['Cash', 'Mixed'])) {
                $cashAmount = $request->payment_method === 'Cash' ? $totalAmount : ($request->cash_received ?? 0);
                
                CashMovement::create([
                    'cash_register_id' => $request->cash_register_id,
                    'type' => 'Sale',
                    'amount' => $cashAmount,
                    'reason' => 'Sale: ' . $sale->sale_number,
                    'reference_id' => $sale->id,
                    'reference_type' => 'Sale',
                    'recorded_by' => auth()->id(),
                    'movement_date' => now(),
                ]);
            }

            DB::commit();

            $sale->load([
                'items.product',
                'items.variant',
                'cashier',
                'salesStaff',
                'branch'
            ]);

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
     * Check discount authorization based on user role
     */
    private function checkDiscountAuthorization($user, $discountPercentage)
    {
        $role = $user->role->role_name ?? '';

        // Super Admin: unlimited discount
        if ($role === 'Super Admin') {
            return [
                'allowed' => true,
                'max_discount' => 100
            ];
        }

        // Branch Manager: up to 30% discount
        if ($role === 'Branch Manager') {
            if ($discountPercentage <= 30) {
                return [
                    'allowed' => true,
                    'max_discount' => 30
                ];
            }
            return [
                'allowed' => false,
                'message' => 'Branch Manager can only approve discounts up to 30%',
                'max_discount' => 30
            ];
        }

        // Cashier: up to 10% discount
        if ($role === 'Cashier') {
            if ($discountPercentage <= 10) {
                return [
                    'allowed' => true,
                    'max_discount' => 10
                ];
            }
            return [
                'allowed' => false,
                'message' => 'Cashier can only approve discounts up to 10%. Manager authorization required.',
                'max_discount' => 10
            ];
        }

        // Default: no discount allowed
        return [
            'allowed' => false,
            'message' => 'You are not authorized to apply discounts',
            'max_discount' => 0
        ];
    }

    /**
     * Calculate employee discount (e.g., 20%)
     */
    private function calculateEmployeeDiscount($amount)
    {
        $discountPercentage = 20; // 20% employee discount
        return ($amount * $discountPercentage) / 100;
    }

    /**
     * Apply coupon
     */
    private function applyCoupon($couponCode, $totalAmount, $branchId, $customerId)
    {
        $coupon = Coupon::where('coupon_code', $couponCode)->first();

        if (!$coupon) {
            return ['valid' => false, 'message' => 'Invalid coupon code'];
        }

        // Validate coupon
        $validation = $coupon->isValid($branchId, $totalAmount);
        if (!$validation['valid']) {
            return $validation;
        }

        // Check usage limit per user
        if ($customerId && $coupon->usage_limit_per_user) {
            $userUsage = CouponUsage::where('coupon_id', $coupon->id)
                ->where('user_id', $customerId)
                ->count();

            if ($userUsage >= $coupon->usage_limit_per_user) {
                return ['valid' => false, 'message' => 'You have already used this coupon'];
            }
        }

        // Calculate discount
        $discount = $coupon->calculateDiscount($totalAmount);

        return [
            'valid' => true,
            'discount' => $discount,
            'coupon' => $coupon
        ];
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
                'salesStaff',
                'customer',
                'items.product.images',
                'items.variant',
                'returns',
                'couponUsage.coupon',
                'discountAuthorizations.authorizedBy'
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
     * Generate receipt
     */
    public function generateReceipt($id)
    {
        try {
            $sale = Sale::with([
                'branch',
                'cashier',
                'salesStaff',
                'customer',
                'items.product',
                'items.variant'
            ])->findOrFail($id);

            // Prepare receipt data
            $receiptData = [
                'sale_number' => $sale->sale_number,
                'branch' => $sale->branch->branch_name,
                'branch_address' => $sale->branch->address,
                'branch_phone' => $sale->branch->phone,
                'date' => $sale->sale_date->format('d/m/Y H:i'),
                'cashier' => $sale->cashier->name,
                'cashier_id' => $sale->cashier->employee_id,
                'sales_staff' => $sale->salesStaff ? $sale->salesStaff->name : null,
                'sales_staff_id' => $sale->salesStaff ? $sale->salesStaff->employee_id : null,
                'is_gift' => $sale->is_gift,
                'items' => [],
                'subtotal' => $sale->subtotal,
                'discount' => $sale->discount_amount,
                'coupon_discount' => $sale->coupon_discount,
                'employee_discount' => $sale->employee_discount_amount,
                'tax' => $sale->tax_amount,
                'total' => $sale->total_amount,
                'payment_method' => $sale->payment_method,
                'cash_received' => $sale->cash_received,
                'change_given' => $sale->change_given,
            ];

            // Add items (hide prices if gift)
            foreach ($sale->items as $item) {
                $itemData = [
                    'product_name' => $item->product->product_name,
                    'variant' => $item->variant ? $item->variant->variant_name : null,
                    'quantity' => $item->quantity,
                ];

                // Only show prices if not a gift
                if (!$sale->is_gift) {
                    $itemData['unit_price'] = $item->unit_price;
                    $itemData['discount'] = $item->discount_amount;
                    $itemData['total'] = $item->total;
                }

                $receiptData['items'][] = $itemData;
            }

            return response()->json([
                'success' => true,
                'data' => $receiptData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate receipt',
                'error' => $e->getMessage()
            ], 500);
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
                'total_coupon_discount' => $query->sum('coupon_discount'),
                'total_employee_discount' => $query->sum('employee_discount_amount'),
                'gift_purchases' => Sale::whereBetween('sale_date', [$startDate, $endDate])
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->where('is_gift', true)
                    ->count(),
                'employee_purchases' => Sale::whereBetween('sale_date', [$startDate, $endDate])
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->where('is_employee_purchase', true)
                    ->count(),
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
}