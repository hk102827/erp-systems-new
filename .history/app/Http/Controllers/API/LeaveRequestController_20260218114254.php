<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class LeaveRequestController extends Controller
{
    /**
     * Get all leave requests
     */
    public function index(Request $request)
    {
        try {
            $query = LeaveRequest::with(['user', 'leaveType', 'approvedBy']);

            // Filter by user
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by leave type
            if ($request->has('leave_type_id')) {
                $query->where('leave_type_id', $request->leave_type_id);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('start_date', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('end_date', '<=', $request->end_date);
            }

            $leaveRequests = $query->latest()->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $leaveRequests
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch leave requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single leave request
     */
    public function show($id)
    {
        try {
            $leaveRequest = LeaveRequest::with(['user', 'leaveType', 'approvedBy'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $leaveRequest
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Leave request not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Create leave request
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get leave type
            $leaveType = LeaveType::findOrFail($request->leave_type_id);

            // Calculate total days
            $startDate = \Carbon\Carbon::parse($request->start_date);
            $endDate = \Carbon\Carbon::parse($request->end_date);
            $totalDays = $startDate->diffInDays($endDate) + 1;

            // Check if user has exceeded max days per year
            $year = $startDate->format('Y');
            $usedDays = LeaveRequest::where('user_id', $request->user_id)
                ->where('leave_type_id', $request->leave_type_id)
                ->where('status', 'Approved')
                ->whereYear('start_date', $year)
                ->sum('total_days');

            if (($usedDays + $totalDays) > $leaveType->max_days_per_year) {
                return response()->json([
                    'success' => false,
                    'message' => "Leave request exceeds maximum allowed days ({$leaveType->max_days_per_year} days per year). You have used {$usedDays} days."
                ], 400);
            }

            // Check for overlapping leave requests
            $overlapping = LeaveRequest::where('user_id', $request->user_id)
                ->where('status', '!=', 'Rejected')
                ->where('status', '!=', 'Cancelled')
                ->where(function($q) use ($request) {
                    $q->whereBetween('start_date', [$request->start_date, $request->end_date])
                      ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                      ->orWhere(function($q2) use ($request) {
                          $q2->where('start_date', '<=', $request->start_date)
                             ->where('end_date', '>=', $request->end_date);
                      });
                })
                ->exists();

            if ($overlapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a leave request for this date range'
                ], 400);
            }

            // Create leave request
            $leaveRequest = LeaveRequest::create([
                'user_id' => $request->user_id,
                'leave_type_id' => $request->leave_type_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'total_days' => $totalDays,
                'reason' => $request->reason,
                'status' => $leaveType->requires_approval ? 'Pending' : 'Approved',
            ]);

            $leaveRequest->load(['user', 'leaveType']);

            return response()->json([
                'success' => true,
                'message' => 'Leave request submitted successfully',
                'data' => $leaveRequest
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve leave request
     */
    public function approve($id)
    {
        try {
            $leaveRequest = LeaveRequest::findOrFail($id);

            if ($leaveRequest->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending leave requests can be approved'
                ], 400);
            }

            $leaveRequest->update([
                'status' => 'Approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            $leaveRequest->load(['user', 'leaveType', 'approvedBy']);

            return response()->json([
                'success' => true,
                'message' => 'Leave request approved successfully',
                'data' => $leaveRequest
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject leave request
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
            $leaveRequest = LeaveRequest::findOrFail($id);

            if ($leaveRequest->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending leave requests can be rejected'
                ], 400);
            }

            $leaveRequest->update([
                'status' => 'Rejected',
                'approved_by' => auth()->id(),
                'rejection_reason' => $request->rejection_reason,
                'approved_at' => now(),
            ]);

            $leaveRequest->load(['user', 'leaveType', 'approvedBy']);

            return response()->json([
                'success' => true,
                'message' => 'Leave request rejected',
                'data' => $leaveRequest
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel leave request (by user)
     */
    public function cancel($id)
    {
        try {
            $leaveRequest = LeaveRequest::findOrFail($id);

            // Check if user owns this request
            if ($leaveRequest->user_id !== auth()->id() && !auth()->user()->isSuperAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only cancel your own leave requests'
                ], 403);
            }

            if (!in_array($leaveRequest->status, ['Pending', 'Approved'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending or approved leave requests can be cancelled'
                ], 400);
            }

            $leaveRequest->update(['status' => 'Cancelled']);

            return response()->json([
                'success' => true,
                'message' => 'Leave request cancelled successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get leave balance for a user
     */
    public function getLeaveBalance($userId)
    {
        try {
            $year = now()->format('Y');

            $leaveTypes = LeaveType::where('is_active', true)->get();

            $balance = $leaveTypes->map(function($leaveType) use ($userId, $year) {
                $usedDays = LeaveRequest::where('user_id', $userId)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('status', 'Approved')
                    ->whereYear('start_date', $year)
                    ->sum('total_days');

                $pendingDays = LeaveRequest::where('user_id', $userId)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('status', 'Pending')
                    ->whereYear('start_date', $year)
                    ->sum('total_days');

                return [
                    'leave_type' => $leaveType->leave_type_name,
                    'max_days' => $leaveType->max_days_per_year,
                    'used_days' => $usedDays,
                    'pending_days' => $pendingDays,
                    'available_days' => $leaveType->max_days_per_year - $usedDays - $pendingDays,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'year' => $year,
                    'balance' => $balance,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch leave balance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get leave statistics
     */
    public function statistics(Request $request)
    {
        try {
            $year = $request->get('year', now()->format('Y'));

            $stats = [
                'total_requests' => LeaveRequest::whereYear('start_date', $year)->count(),
                'pending' => LeaveRequest::whereYear('start_date', $year)->where('status', 'Pending')->count(),
                'approved' => LeaveRequest::whereYear('start_date', $year)->where('status', 'Approved')->count(),
                'rejected' => LeaveRequest::whereYear('start_date', $year)->where('status', 'Rejected')->count(),
                'cancelled' => LeaveRequest::whereYear('start_date', $year)->where('status', 'Cancelled')->count(),
                'total_days_requested' => LeaveRequest::whereYear('start_date', $year)->sum('total_days'),
                'total_days_approved' => LeaveRequest::whereYear('start_date', $year)->where('status', 'Approved')->sum('total_days'),
                'by_leave_type' => DB::table('leave_requests')
                    ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.id')
                    ->whereYear('leave_requests.start_date', $year)
                    ->where('leave_requests.status', 'Approved')
                    ->select(
                        'leave_types.leave_type_name',
                        DB::raw('COUNT(*) as count'),
                        DB::raw('SUM(leave_requests.total_days) as total_days')
                    )
                    ->groupBy('leave_types.leave_type_name')
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