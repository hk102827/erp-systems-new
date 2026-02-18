<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function hrDashboard(Request $request)
    {
        try {
            $today = now()->format('Y-m-d');
            $branchId = $request->get('branch_id');

            // Total Employees
            $totalEmployees = User::where('is_active', true);
            if ($branchId) {
                $totalEmployees->where('branch_id', $branchId);
            }
            $totalEmployees = $totalEmployees->count();

            // Today's Attendance
            $attendanceQuery = Attendance::where('attendance_date', $today);
            if ($branchId) {
                $attendanceQuery->where('branch_id', $branchId);
            }

            $presentToday = (clone $attendanceQuery)->whereIn('status', ['Present', 'Late'])->count();
            $onLeaveToday = (clone $attendanceQuery)->where('status', 'On Leave')->count();
            $absentToday = (clone $attendanceQuery)->where('status', 'Absent')->count();

            // Active, On Leave, Absent (overall status)
            $active = $totalEmployees - $onLeaveToday - $absentToday;
            
            // Pending Leave Requests
            $pendingLeaveRequests = LeaveRequest::where('status', 'Pending');
            if ($branchId) {
                $pendingLeaveRequests->whereHas('user', function($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                });
            }
            $pendingLeaveRequests = $pendingLeaveRequests->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_employees' => $totalEmployees,
                    'present_today' => $presentToday,
                    'on_leave_today' => $onLeaveToday,
                    'absent_today' => $absentToday,
                    'active' => $active,
                    'pending_leave_requests' => $pendingLeaveRequests,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}