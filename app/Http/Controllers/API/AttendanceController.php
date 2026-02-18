<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * Get all attendance records
     */
    public function index(Request $request)
    {
        try {
            $query = Attendance::with(['user', 'branch']);

            // Filter by user
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by branch
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('attendance_date', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('attendance_date', '<=', $request->end_date);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $attendances = $query->latest('attendance_date')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $attendances
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark attendance (check-in)
     */
    public function checkIn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'branch_id' => 'required|exists:branches,id',
            'device_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $today = now()->format('Y-m-d');

            // Check if already checked in
            $existing = Attendance::where('user_id', $request->user_id)
                ->where('attendance_date', $today)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Already checked in today',
                    'data' => $existing
                ], 400);
            }

            $attendance = Attendance::create([
                'user_id' => $request->user_id,
                'branch_id' => $request->branch_id,
                'attendance_date' => $today,
                'check_in' => now()->format('H:i'),
                'status' => 'Present',
                'device_id' => $request->device_id,
            ]);

            $attendance->load(['user', 'branch']);

            return response()->json([
                'success' => true,
                'message' => 'Check-in successful',
                'data' => $attendance
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check in',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check-out
     */
    public function checkOut(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $today = now()->format('Y-m-d');

            $attendance = Attendance::where('user_id', $request->user_id)
                ->where('attendance_date', $today)
                ->whereNull('check_out')
                ->firstOrFail();

            $attendance->update([
                'check_out' => now()->format('H:i'),
            ]);

            $attendance->load(['user', 'branch']);

            return response()->json([
                'success' => true,
                'message' => 'Check-out successful',
                'data' => [
                    'attendance' => $attendance,
                    'total_hours' => $attendance->getFormattedTotalHours(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No active check-in found for today',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Manual attendance entry
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'branch_id' => 'required|exists:branches,id',
            'attendance_date' => 'required|date',
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i|after:check_in',
            'status' => 'required|in:Present,Absent,Late,Half Day,On Leave',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $attendance = Attendance::create($request->all());

            $attendance->load(['user', 'branch']);

            return response()->json([
                'success' => true,
                'message' => 'Attendance recorded successfully',
                'data' => $attendance
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record attendance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance summary for a user
     */
    public function getUserSummary($userId, Request $request)
    {
        try {
            $month = $request->get('month', now()->format('Y-m'));

            $attendances = Attendance::where('user_id', $userId)
                ->whereRaw("DATE_FORMAT(attendance_date, '%Y-%m') = ?", [$month])
                ->get();

            $summary = [
                'month' => $month,
                'total_days' => $attendances->count(),
                'present' => $attendances->where('status', 'Present')->count(),
                'absent' => $attendances->where('status', 'Absent')->count(),
                'late' => $attendances->where('status', 'Late')->count(),
                'half_day' => $attendances->where('status', 'Half Day')->count(),
                'on_leave' => $attendances->where('status', 'On Leave')->count(),
                'total_hours' => $attendances->sum('total_hours'),
                'average_hours_per_day' => $attendances->avg('total_hours'),
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

    /**
     * Get attendance report (all employees)
     */
    public function report(Request $request)
    {
        try {
            $month = $request->get('month', now()->format('Y-m'));
            $branchId = $request->get('branch_id');

            $query = DB::table('attendances')
                ->join('users', 'attendances.user_id', '=', 'users.id')
                ->join('branches', 'attendances.branch_id', '=', 'branches.id')
                ->whereRaw("DATE_FORMAT(attendance_date, '%Y-%m') = ?", [$month])
                ->select(
                    'users.id as user_id',
                    'users.name',
                    'users.employee_id',
                    'branches.branch_name',
                    DB::raw('COUNT(*) as total_days'),
                    DB::raw('SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) as present'),
                    DB::raw('SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent'),
                    DB::raw('SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) as late'),
                    DB::raw('SUM(CASE WHEN status = "On Leave" THEN 1 ELSE 0 END) as on_leave'),
                    DB::raw('SUM(total_hours) as total_hours')
                )
                ->groupBy('users.id', 'users.name', 'users.employee_id', 'branches.branch_name');

            if ($branchId) {
                $query->where('attendances.branch_id', $branchId);
            }

            $report = $query->get();

            return response()->json([
                'success' => true,
                'data' => $report
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}