<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeaveTypeController extends Controller
{
    /**
     * Get all leave types
     */
    public function index()
    {
        try {
            $leaveTypes = LeaveType::where('is_active', true)->get();

            return response()->json([
                'success' => true,
                'data' => $leaveTypes
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch leave types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create leave type
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'leave_type_name' => 'required|string|max:255|unique:leave_types,leave_type_name',
            'max_days_per_year' => 'required|integer|min:0',
            'is_paid' => 'boolean',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
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
            $leaveType = LeaveType::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Leave type created successfully',
                'data' => $leaveType
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create leave type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update leave type
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'leave_type_name' => 'required|string|max:255|unique:leave_types,leave_type_name,' . $id,
            'max_days_per_year' => 'required|integer|min:0',
            'is_paid' => 'boolean',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
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
            $leaveType = LeaveType::findOrFail($id);
            $leaveType->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Leave type updated successfully',
                'data' => $leaveType
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update leave type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete leave type
     */
    public function destroy($id)
    {
        try {
            $leaveType = LeaveType::findOrFail($id);

            // Check if leave type has any requests
            if ($leaveType->leaveRequests()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete leave type. It has associated leave requests.'
                ], 400);
            }

            $leaveType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Leave type deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete leave type',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}