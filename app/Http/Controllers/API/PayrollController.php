<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Bonus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    /**
     * Get all payrolls
     */
    public function index(Request $request)
    {
        try {
            $query = Payroll::with(['user', 'approvedBy']);

            // Filter by user
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by month
            if ($request->has('payroll_month')) {
                $query->where('payroll_month', $request->payroll_month);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $payrolls = $query->latest('payroll_month')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $payrolls
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payrolls',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single payroll
     */
    public function show($id)
    {
        try {
            $payroll = Payroll::with(['user', 'approvedBy'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $payroll
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Generate payroll for a user
     */
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'payroll_month' => 'required|date_format:Y-m',
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
            $user = User::findOrFail($request->user_id);
            $month = $request->payroll_month;

            // Check if payroll already exists
            $existing = Payroll::where('user_id', $request->user_id)
                ->where('payroll_month', $month)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll already exists for this month'
                ], 400);
            }

            // Get attendance data for the month
            $attendances = Attendance::where('user_id', $request->user_id)
                ->whereRaw("DATE_FORMAT(attendance_date, '%Y-%m') = ?", [$month])
                ->get();

            $presentDays = $attendances->whereIn('status', ['Present', 'Late'])->count();
            $absentDays = $attendances->where('status', 'Absent')->count();
            $leaveDays = $attendances->where('status', 'On Leave')->count();

            // Calculate working days in month
            $date = \Carbon\Carbon::parse($month . '-01');
            $workingDays = $date->daysInMonth;

            // Calculate allowances
            $totalAllowances = $user->transportation_allowance
                + $user->housing_allowance
                + $user->communication_allowance
                + $user->meal_allowance
                + $user->accommodation_allowance;

            // Get bonuses for the month
            $bonuses = Bonus::where('user_id', $request->user_id)
                ->whereRaw("DATE_FORMAT(bonus_date, '%Y-%m') = ?", [$month])
                ->sum('amount');

            // Calculate deductions (for absent days)
            $perDaySalary = ($user->basic_salary + $totalAllowances) / $workingDays;
            $totalDeductions = $absentDays * $perDaySalary;

            // Calculate net salary
            $netSalary = $user->basic_salary + $totalAllowances + $bonuses - $totalDeductions;

            // Create payroll
            $payroll = Payroll::create([
                'user_id' => $request->user_id,
                'payroll_month' => $month,
                'basic_salary' => $user->basic_salary,
                'total_allowances' => $totalAllowances,
                'total_bonuses' => $bonuses,
                'total_deductions' => $totalDeductions,
                'net_salary' => $netSalary,
                'working_days' => $workingDays,
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'leave_days' => $leaveDays,
                'status' => 'Draft',
            ]);

            DB::commit();

            $payroll->load(['user', 'approvedBy']);

            return response()->json([
                'success' => true,
                'message' => 'Payroll generated successfully',
                'data' => $payroll
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate payroll',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate payroll for all employees
     */
    public function generateBulk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payroll_month' => 'required|date_format:Y-m',
            'branch_id' => 'nullable|exists:branches,id',
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
            $month = $request->payroll_month;

            $query = User::where('is_active', true);

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            $users = $query->get();

            $generated = 0;
            $skipped = 0;
            $errors = [];

            foreach ($users as $user) {
                try {
                    // Check if payroll already exists
                    $existing = Payroll::where('user_id', $user->id)
                        ->where('payroll_month', $month)
                        ->exists();

                    if ($existing) {
                        $skipped++;
                        continue;
                    }

                    // Get attendance data
                    $attendances = Attendance::where('user_id', $user->id)
                        ->whereRaw("DATE_FORMAT(attendance_date, '%Y-%m') = ?", [$month])
                        ->get();

                    $presentDays = $attendances->whereIn('status', ['Present', 'Late'])->count();
                    $absentDays = $attendances->where('status', 'Absent')->count();
                    $leaveDays = $attendances->where('status', 'On Leave')->count();

                    $date = \Carbon\Carbon::parse($month . '-01');
                    $workingDays = $date->daysInMonth;

                    $totalAllowances = $user->transportation_allowance
                        + $user->housing_allowance
                        + $user->communication_allowance
                        + $user->meal_allowance
                        + $user->accommodation_allowance;

                    $bonuses = Bonus::where('user_id', $user->id)
                        ->whereRaw("DATE_FORMAT(bonus_date, '%Y-%m') = ?", [$month])
                        ->sum('amount');

                    $perDaySalary = ($user->basic_salary + $totalAllowances) / $workingDays;
                    $totalDeductions = $absentDays * $perDaySalary;
                    $netSalary = $user->basic_salary + $totalAllowances + $bonuses - $totalDeductions;

                    Payroll::create([
                        'user_id' => $user->id,
                        'payroll_month' => $month,
                        'basic_salary' => $user->basic_salary,
                        'total_allowances' => $totalAllowances,
                        'total_bonuses' => $bonuses,
                        'total_deductions' => $totalDeductions,
                        'net_salary' => $netSalary,
                        'working_days' => $workingDays,
                        'present_days' => $presentDays,
                        'absent_days' => $absentDays,
                        'leave_days' => $leaveDays,
                        'status' => 'Draft',
                    ]);

                    $generated++;

                } catch (\Exception $e) {
                    $errors[] = "User {$user->name} (ID: {$user->id}): " . $e->getMessage();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bulk payroll generation completed',
                'data' => [
                    'generated' => $generated,
                    'skipped' => $skipped,
                    'errors' => $errors,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate bulk payroll',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve payroll
     */
    public function approve($id)
    {
        try {
            $payroll = Payroll::findOrFail($id);

            if ($payroll->status !== 'Draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft payrolls can be approved'
                ], 400);
            }

            $payroll->update([
                'status' => 'Approved',
                'approved_by' => auth()->id(),
            ]);

            $payroll->load(['user', 'approvedBy']);

            return response()->json([
                'success' => true,
                'message' => 'Payroll approved successfully',
                'data' => $payroll
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve payroll',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark payroll as paid
     */
    public function markAsPaid(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $payroll = Payroll::findOrFail($id);

            if ($payroll->status !== 'Approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved payrolls can be marked as paid'
                ], 400);
            }

            $payroll->update([
                'status' => 'Paid',
                'payment_date' => $request->payment_date,
            ]);

            $payroll->load(['user', 'approvedBy']);

            return response()->json([
                'success' => true,
                'message' => 'Payroll marked as paid successfully',
                'data' => $payroll
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark payroll as paid',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payroll statistics
     */
    public function statistics(Request $request)
    {
        try {
            $month = $request->get('payroll_month', now()->format('Y-m'));

            $stats = [
                'month' => $month,
                'total_payrolls' => Payroll::where('payroll_month', $month)->count(),
                'draft' => Payroll::where('payroll_month', $month)->where('status', 'Draft')->count(),
                'approved' => Payroll::where('payroll_month', $month)->where('status', 'Approved')->count(),
                'paid' => Payroll::where('payroll_month', $month)->where('status', 'Paid')->count(),
                'total_basic_salary' => Payroll::where('payroll_month', $month)->sum('basic_salary'),
                'total_allowances' => Payroll::where('payroll_month', $month)->sum('total_allowances'),
                'total_bonuses' => Payroll::where('payroll_month', $month)->sum('total_bonuses'),
                'total_deductions' => Payroll::where('payroll_month', $month)->sum('total_deductions'),
                'total_net_salary' => Payroll::where('payroll_month', $month)->sum('net_salary'),
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
     * Delete payroll (only draft)
     */
    public function destroy($id)
    {
        try {
            $payroll = Payroll::findOrFail($id);

            if ($payroll->status !== 'Draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft payrolls can be deleted'
                ], 400);
            }

            $payroll->delete();

            return response()->json([
                'success' => true,
                'message' => 'Payroll deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payroll',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}