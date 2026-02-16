<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PermissionController extends Controller
{
    /**
     * Get all permissions
     */
    public function index()
    {
        try {
            $permissions = Permission::all()->groupBy('module_name');

            return response()->json([
                'success' => true,
                'data' => $permissions
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single permission
     */
    public function show($id)
    {
        try {
            $permission = Permission::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $permission
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Create new permission
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'permission_name' => 'required|string|max:255|unique:permissions,permission_name',
            'module_name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $permission = Permission::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Permission created successfully',
                'data' => $permission
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update permission
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'permission_name' => 'required|string|max:255|unique:permissions,permission_name,' . $id,
            'module_name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $permission = Permission::findOrFail($id);
            $permission->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Permission updated successfully',
                'data' => $permission
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft delete permission
     */
    public function destroy($id)
    {
        try {
            $permission = Permission::findOrFail($id);
            $permission->delete();

            return response()->json([
                'success' => true,
                'message' => 'Permission deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get permissions by module
     */
    public function getByModule($moduleName)
    {
        try {
            $permissions = Permission::where('module_name', $moduleName)->get();

            return response()->json([
                'success' => true,
                'data' => $permissions
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}