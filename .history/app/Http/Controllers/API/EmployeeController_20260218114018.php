<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmployeeController extends Controller
{
    /**
     * Get all employees (HR view)
     */
    public function index(Request $request)
    {
        try {
            $query = User::with(['role', 'branch', 'documents']);

            // Filter by branch
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            // Filter by department
            if ($request->has('department')) {
                $query->where('department', $request->department);
            }

            // Filter by status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('employee_id', 'like', "%{$search}%")
                      ->orWhere('national_id', 'like', "%{$search}%");
                });
            }

            $employees = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $employees
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch employees',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single employee details
     */
    public function show($id)
    {
        try {
            $employee = User::with([
                'role.permissions',
                'branch',
                'documents',
                'attendances' => function($q) {
                    $q->latest()->take(30);
                },
                'leaveRequests' => function($q) {
                    $q->latest()->take(10);
                },
                'bonuses',
                'payrolls' => function($q) {
                    $q->latest()->take(12);
                }
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $employee
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update employee HR information
     */
    public function updateHRInfo(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'national_id' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:Male,Female',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',
            'joining_date' => 'nullable|date',
            'job_title' => 'nullable|string',
            'department' => 'nullable|string',
            'basic_salary' => 'nullable|numeric|min:0',
            'transportation_allowance' => 'nullable|numeric|min:0',
            'housing_allowance' => 'nullable|numeric|min:0',
            'communication_allowance' => 'nullable|numeric|min:0',
            'meal_allowance' => 'nullable|numeric|min:0',
            'accommodation_allowance' => 'nullable|numeric|min:0',
            'emergency_contact_name' => 'nullable|string',
            'emergency_contact_phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $employee = User::findOrFail($id);
            $employee->update($request->all());

            $employee->load(['role', 'branch']);

            return response()->json([
                'success' => true,
                'message' => 'Employee HR information updated successfully',
                'data' => $employee
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employees by department
     */
    public function getByDepartment($department)
    {
        try {
            $employees = User::where('department', $department)
                ->where('is_active', true)
                ->with(['role', 'branch'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $employees
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch employees',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee statistics
     */
    public function statistics()
    {
        try {
            $stats = [
                'total_employees' => User::count(),
                'active_employees' => User::where('is_active', true)->count(),
                'inactive_employees' => User::where('is_active', false)->count(),
                'by_department' => User::selectRaw('department, COUNT(*) as count')
                    ->whereNotNull('department')
                    ->groupBy('department')
                    ->get(),
                'by_gender' => User::selectRaw('gender, COUNT(*) as count')
                    ->whereNotNull('gender')
                    ->groupBy('gender')
                    ->get(),
                'by_marital_status' => User::selectRaw('marital_status, COUNT(*) as count')
                    ->whereNotNull('marital_status')
                    ->groupBy('marital_status')
                    ->get(),
                'average_salary' => User::where('basic_salary', '>', 0)->avg('basic_salary'),
                'total_salary_expense' => User::where('is_active', true)->sum('basic_salary'),
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