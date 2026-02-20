<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ShiftReport;
use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\ReturnModel;
use App\Models\CashMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ShiftReportController extends Controller
{
    /**
     * Get all shift reports
     */
    public function index(Request $request)
    {
        try {
            $query = ShiftReport::with(['cashRegister', 'branch', 'user']);

            // Filter by branch
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            // Filter by user
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('shift_start', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('shift_start', '<=', $request->end_date);
            }

            $reports = $query->latest('shift_start')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $reports
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch shift reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate shift report (when closing cash register)
     */
    public function generate($cashRegisterId)
    {
        DB::beginTransaction();

        try {
            $register = CashRegister::findOrFail($cashRegisterId);

            // Check if register is closed
            if ($register->status !== 'Closed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cash register must be closed before generating shift report'
                ], 400);
            }

            // Calculate sales summary
            $sales = Sale::where('cash_register_id', $cashRegisterId)
                ->where('status', 'Completed')
                ->get();

            $totalTransactions = $sales->count();
            $totalSales = $sales->sum('total_amount');
            $totalDiscounts = $sales->sum('discount_amount') + $sales->sum('coupon_discount') + $sales->sum('employee_discount_amount');

            // Calculate returns
            $returns = ReturnModel::whereIn('sale_id', $sales->pluck('id'))
                ->where('status', 'Approved')
                ->sum('return_amount');

            $netSales = $totalSales - $returns;

            // Payment breakdown
            $cashSales = $sales->where('payment_method', 'Cash')->sum('total_amount');
            $cardSales = $sales->where('payment_method', 'Card')->sum('total_amount');
            $knetSales = $sales->where('payment_method', 'K-Net')->sum('total_amount');
            $mobileSales = $sales->where('payment_method', 'Mobile Payment')->sum('total_amount');

            // Mixed payments - add cash portion
            $mixedSales = $sales->where('payment_method', 'Mixed');
            foreach ($mixedSales as $sale) {
                $cashSales += $sale->cash_received ?? 0;
                $cardSales += $sale->card_amount ?? 0;
            }

            // Cash movements
            $cashMovements = CashMovement::where('cash_register_id', $cashRegisterId)->get();
            $cashIn = $cashMovements->where('type', 'Cash In')->sum('amount');
            $cashOut = $cashMovements->where('type', 'Cash Out')->sum('amount');

            // Create shift report
            $report = ShiftReport::create([
                'cash_register_id' => $cashRegisterId,
                'branch_id' => $register->branch_id,
                'user_id' => $register->user_id,
                'shift_start' => $register->opened_at,
                'shift_end' => $register->closed_at,
                'opening_cash' => $register->opening_balance,
                'closing_cash' => $register->closing_balance,
                'expected_cash' => $register->expected_balance,
                'cash_difference' => $register->difference,
                'total_transactions' => $totalTransactions,
                'total_sales' => $totalSales,
                'total_discounts' => $totalDiscounts,
                'total_returns' => $returns,
                'net_sales' => $netSales,
                'cash_sales' => $cashSales,
                'card_sales' => $cardSales,
                'knet_sales' => $knetSales,
                'mobile_payment_sales' => $mobileSales,
                'cash_in' => $cashIn,
                'cash_out' => $cashOut,
                'status' => 'Closed',
            ]);

            DB::commit();

            $report->load(['cashRegister', 'branch', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Shift report generated successfully',
                'data' => $report
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate shift report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get shift report details
     */
    public function show($id)
    {
        try {
            $report = ShiftReport::with([
                'cashRegister',
                'branch',
                'user'
            ])->findOrFail($id);

            // Get detailed transactions
            $sales = Sale::where('cash_register_id', $report->cash_register_id)
                ->with(['items.product', 'cashier', 'customer'])
                ->get();

            $cashMovements = CashMovement::where('cash_register_id', $report->cash_register_id)
                ->with('recordedBy')
                ->get();

            $report->sales = $sales;
            $report->cash_movements = $cashMovements;

            return response()->json([
                'success' => true,
                'data' => $report
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Shift report not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Export shift report (PDF/Excel)
     */
    public function export($id, $format = 'pdf')
    {
        try {
            $report = ShiftReport::with([
                'cashRegister',
                'branch',
                'user'
            ])->findOrFail($id);

            // Get detailed data
            $sales = Sale::where('cash_register_id', $report->cash_register_id)
                ->with(['items.product', 'cashier'])
                ->get();

            $cashMovements = CashMovement::where('cash_register_id', $report->cash_register_id)
                ->with('recordedBy')
                ->get();

            $data = [
                'report' => $report,
                'sales' => $sales,
                'cash_movements' => $cashMovements,
            ];

            // Here you would integrate PDF/Excel generation library
            // For now, return JSON data

            return response()->json([
                'success' => true,
                'message' => 'Shift report export ready',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export shift report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get daily summary
     */
    public function dailySummary(Request $request)
    {
        try {
            $date = $request->get('date', today()->format('Y-m-d'));
            $branchId = $request->get('branch_id');

            $query = ShiftReport::whereDate('shift_start', $date);

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            $reports = $query->get();

            $summary = [
                'date' => $date,
                'total_shifts' => $reports->count(),
                'total_transactions' => $reports->sum('total_transactions'),
                'total_sales' => $reports->sum('total_sales'),
                'total_discounts' => $reports->sum('total_discounts'),
                'total_returns' => $reports->sum('total_returns'),
                'net_sales' => $reports->sum('net_sales'),
                'cash_sales' => $reports->sum('cash_sales'),
                'card_sales' => $reports->sum('card_sales'),
                'knet_sales' => $reports->sum('knet_sales'),
                'mobile_payment_sales' => $reports->sum('mobile_payment_sales'),
                'total_cash_difference' => $reports->sum('cash_difference'),
                'shifts' => $reports,
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch daily summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}