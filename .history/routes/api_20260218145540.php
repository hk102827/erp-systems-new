<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\PermissionController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\BranchController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\InventoryController;
use App\Http\Controllers\API\StockTransferController;
use App\Http\Controllers\API\DamagedItemController;
use App\Http\Controllers\API\EmployeeController;
use App\Http\Controllers\API\EmployeeDocumentController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\LeaveTypeController;
use App\Http\Controllers\API\LeaveRequestController;
use App\Http\Controllers\API\BonusController;
use App\Http\Controllers\API\PayrollController;
use App\Http\Controllers\API\DashboardController;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (no authentication required)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']); // Remove this if only admin can create users

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {

    // Auth Routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::get('/active-sessions', [AuthController::class, 'getActiveSessions']);
    Route::post('/revoke-all-tokens', [AuthController::class, 'revokeAllTokens']);

    // Auth user info (alternative to /profile)
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => $request->user()->load(['role.permissions', 'branch'])
        ]);
    });


    Route::get('/roles', [RoleController::class, 'index']);

    // Role Management Routes (Super Admin only)
    Route::middleware(['role:Super Admin'])->group(function () {
        // Roles
        Route::get('/roles/{id}', [RoleController::class, 'show']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{id}', [RoleController::class, 'update']);
        Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
        Route::post('/roles/{id}/assign-permissions', [RoleController::class, 'assignPermissions']);
    });

    // Permission Management Routes (Super Admin only)
    Route::middleware(['role:Super Admin'])->group(function () {
        // Permissions
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::get('/permissions/{id}', [PermissionController::class, 'show']);
        Route::post('/permissions', [PermissionController::class, 'store']);
        Route::put('/permissions/{id}', [PermissionController::class, 'update']);
        Route::delete('/permissions/{id}', [PermissionController::class, 'destroy']);
        Route::get('/permissions/module/{moduleName}', [PermissionController::class, 'getByModule']);
    });
    Route::middleware(['role:Super Admin'])->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/statistics', [UserController::class, 'statistics']);
        Route::get('/users/deleted', [UserController::class, 'deleted']);
        Route::get('/users/role/{roleId}', [UserController::class, 'getUsersByRole']);
        Route::get('/users/branch/{branchId}', [UserController::class, 'getUsersByBranch']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::post('/users/{id}/restore', [UserController::class, 'restore']);
        Route::post('/users/{id}/change-status', [UserController::class, 'changeStatus']);
        Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
        Route::post('/users/{id}/assign-role', [UserController::class, 'assignRole']);
    });


    Route::get('/branches', [BranchController::class, 'index']);

    // Branch Management Routes (Super Admin only)
    Route::middleware(['role:Super Admin'])->group(function () {
        Route::get('/branches/statistics', [BranchController::class, 'statistics']);
        Route::get('/branches/deleted', [BranchController::class, 'deleted']);
        Route::get('/branches/types', [BranchController::class, 'getBranchTypes']);
        Route::get('/branches/warehouses', [BranchController::class, 'getWarehouses']);
        Route::get('/branches/retail', [BranchController::class, 'getRetailBranches']);
        Route::get('/branches/with-pos', [BranchController::class, 'getBranchesWithPOS']);
        Route::get('/branches/temporary', [BranchController::class, 'getTemporaryBranches']);
        Route::get('/branches/type/{type}', [BranchController::class, 'getByType']);
        Route::get('/branches/{id}', [BranchController::class, 'show']);
        Route::get('/branches/{id}/users', [BranchController::class, 'getBranchUsers']);
        Route::post('/branches', [BranchController::class, 'store']);
        Route::put('/branches/{id}', [BranchController::class, 'update']);
        Route::delete('/branches/delete/{id}', [BranchController::class, 'destroy']);
        Route::post('/branches/{id}/restore', [BranchController::class, 'restore']);
        Route::post('/branches/{id}/change-status', [BranchController::class, 'changeStatus']);
    });

 Route::get('/categories', [CategoryController::class, 'index']);

    // Category Management Routes
    Route::middleware(['permission:view_products'])->group(function () {
        // Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/tree', [CategoryController::class, 'tree']);
        Route::get('/categories/{id}', [CategoryController::class, 'show']);
    });

    Route::middleware(['permission:create_product'])->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
    });

    Route::middleware(['permission:edit_product'])->group(function () {
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
    });

    Route::middleware(['permission:delete_product'])->group(function () {
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
    });

    // Product Management Routes
    Route::middleware(['permission:view_products'])->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/statistics', [ProductController::class, 'statistics']);
        Route::get('/products/low-stock', [ProductController::class, 'getLowStockProducts']);
        Route::get('/products/profitability', [ProductController::class, 'profitabilityReport']);
        Route::get('/products/{id}', [ProductController::class, 'show']);
        Route::get('/products/{id}/stock', [ProductController::class, 'getStock']);
        Route::post('/products/search-barcode', [ProductController::class, 'searchByBarcode']);
    });

    Route::middleware(['permission:create_product'])->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
    });

    Route::middleware(['permission:edit_product'])->group(function () {
         Route::put('/products/{id}', [ProductController::class, 'update']);
         Route::post('/products/{id}/update', [ProductController::class, 'update']); // Add this
        Route::delete('/products/images/{imageId}', [ProductController::class, 'deleteImage']);
        Route::post('/products/{productId}/images/{imageId}/primary', [ProductController::class, 'setPrimaryImage']);
    });

    Route::middleware(['permission:delete_product'])->group(function () {
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    });

    // Inventory Management Routes
    Route::middleware(['permission:view_inventory'])->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::get('/inventory/movements', [InventoryController::class, 'movements']);
        Route::get('/inventory/valuation', [InventoryController::class, 'valuation']);
    });

    Route::middleware(['permission:edit_product'])->group(function () {
        Route::post('/inventory/add-stock', [InventoryController::class, 'addStock']);
        Route::post('/inventory/adjust-stock', [InventoryController::class, 'adjustStock']);
        Route::post('/inventory/update-reorder-point', [InventoryController::class, 'updateReorderPoint']);
    });

    // Stock Transfer Routes
    Route::middleware(['permission:request_transfer'])->group(function () {
        Route::get('/stock-transfers', [StockTransferController::class, 'index']);
        Route::get('/stock-transfers/statistics', [StockTransferController::class, 'statistics']);
        Route::get('/stock-transfers/{id}', [StockTransferController::class, 'show']);
        Route::post('/stock-transfers', [StockTransferController::class, 'store']);
        Route::post('/stock-transfers/{id}/cancel', [StockTransferController::class, 'cancel']);
    });

    Route::middleware(['permission:approve_transfer'])->group(function () {
        Route::post('/stock-transfers/{id}/approve', [StockTransferController::class, 'approve']);
        Route::post('/stock-transfers/{id}/reject', [StockTransferController::class, 'reject']);
        Route::post('/stock-transfers/{id}/complete', [StockTransferController::class, 'complete']);
    });

    // Damaged Items Routes
    Route::middleware(['permission:view_products'])->group(function () {
        Route::get('/damaged-items', [DamagedItemController::class, 'index']);
        Route::get('/damaged-items/statistics', [DamagedItemController::class, 'statistics']);
        Route::get('/damaged-items/{id}', [DamagedItemController::class, 'show']);
        Route::post('/damaged-items', [DamagedItemController::class, 'store']);
        Route::post('/damaged-items/{id}/decision', [DamagedItemController::class, 'makeDecision']);
    });
    // Bulk Discount Management
    Route::middleware(['permission:edit_product'])->group(function () {
        Route::post('/products/discounts/generate-template', [ProductController::class, 'generateDiscountTemplate']);
        Route::post('/products/discounts/import', [ProductController::class, 'importBulkDiscount']);
        Route::get('/discounts/products', [ProductController::class, 'getActiveDiscounts']);
        Route::delete('/discounts/products/{id}', [ProductController::class, 'deleteDiscount']);
    });



    // Employee Management (HR access)
    Route::middleware(['permission:view_users'])->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::get('/employees/statistics', [EmployeeController::class, 'statistics']);
        Route::get('/employees/department/{department}', [EmployeeController::class, 'getByDepartment']);
        Route::get('/employees/{id}', [EmployeeController::class, 'show']);
    });

    Route::middleware(['permission:edit_user'])->group(function () {
        Route::put('/employees/{id}/hr-info', [EmployeeController::class, 'updateHRInfo']);
    });

    // Employee Documents
    Route::get('/employees/{userId}/documents', [EmployeeDocumentController::class, 'index']);
    Route::post('/employee-documents', [EmployeeDocumentController::class, 'store']);
    Route::delete('/employee-documents/{id}', [EmployeeDocumentController::class, 'destroy']);
    Route::get('/employee-documents/expiring', [EmployeeDocumentController::class, 'getExpiringDocuments']);
    Route::get('/employee-documents/expired', [EmployeeDocumentController::class, 'getExpiredDocuments']);

    // Attendance Management
    Route::get('/attendances', [AttendanceController::class, 'index']);
    Route::post('/attendances/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('/attendances/check-out', [AttendanceController::class, 'checkOut']);
    Route::post('/attendances', [AttendanceController::class, 'store']);
    Route::get('/attendances/user/{userId}/summary', [AttendanceController::class, 'getUserSummary']);
    Route::get('/attendances/report', [AttendanceController::class, 'report']);

    // Leave Types (Admin only)
    Route::middleware(['role:Super Admin'])->group(function () {
        Route::get('/leave-types', [LeaveTypeController::class, 'index']);
        Route::post('/leave-types', [LeaveTypeController::class, 'store']);
        Route::put('/leave-types/{id}', [LeaveTypeController::class, 'update']);
        Route::delete('/leave-types/{id}', [LeaveTypeController::class, 'destroy']);
    });

    // Leave Requests
    Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
    Route::get('/leave-requests/statistics', [LeaveRequestController::class, 'statistics']);
    Route::get('/leave-requests/{id}', [LeaveRequestController::class, 'show']);
    Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
    Route::post('/leave-requests/{id}/approve', [LeaveRequestController::class, 'approve']);
    Route::post('/leave-requests/{id}/reject', [LeaveRequestController::class, 'reject']);
    Route::post('/leave-requests/{id}/cancel', [LeaveRequestController::class, 'cancel']);
    Route::get('/leave-requests/user/{userId}/balance', [LeaveRequestController::class, 'getLeaveBalance']);

    // Bonuses
    Route::get('/bonuses', [BonusController::class, 'index']);
    Route::get('/bonuses/user/{userId}/summary', [BonusController::class, 'getUserSummary']);
    Route::post('/bonuses', [BonusController::class, 'store']);
    Route::put('/bonuses/{id}', [BonusController::class, 'update']);
    Route::delete('/bonuses/{id}', [BonusController::class, 'destroy']);

    // Payroll
    Route::get('/payrolls', [PayrollController::class, 'index']);
    Route::get('/payrolls/statistics', [PayrollController::class, 'statistics']);
    Route::get('/payrolls/{id}', [PayrollController::class, 'show']);
    Route::post('/payrolls/generate', [PayrollController::class, 'generate']);
    Route::post('/payrolls/generate-bulk', [PayrollController::class, 'generateBulk']);
    Route::post('/payrolls/{id}/approve', [PayrollController::class, 'approve']);
    Route::post('/payrolls/{id}/mark-paid', [PayrollController::class, 'markAsPaid']);
    Route::delete('/payrolls/{id}', [PayrollController::class, 'destroy']);
    // Dashboard
    Route::get('/dashboard/hr', [DashboardController::class, 'hrDashboard']);

    // Example of using permission middleware
    // Route::middleware(['permission:create_product'])->group(function () {
    //     Route::post('/products', [ProductController::class, 'store']);
    // });
});
// ```

// ---

// ## **Route Structure Summary:**

// ### **Public Routes (No Auth):**
// ```
// POST   /api/login
// POST   /api/register (optional)
// ```

// ### **Protected Routes (Auth Required):**

// **User Info:**
// ```
// GET    /api/user              - Get authenticated user with role & permissions
// POST   /api/logout            - Logout user
// ```

// **Role Management (Super Admin Only):**
// ```
// GET    /api/roles             - Get all roles
// GET    /api/roles/{id}        - Get single role
// POST   /api/roles             - Create new role
// PUT    /api/roles/{id}        - Update role
// DELETE /api/roles/{id}        - Delete role
// POST   /api/roles/{id}/assign-permissions - Assign permissions to role
// ```

// **Permission Management (Super Admin Only):**
// ```
// GET    /api/permissions                    - Get all permissions
// GET    /api/permissions/{id}               - Get single permission
// POST   /api/permissions                    - Create new permission
// PUT    /api/permissions/{id}               - Update permission
// DELETE /api/permissions/{id}               - Delete permission
// GET    /api/permissions/module/{moduleName} - Get permissions by module
// ```

// ---

// ## **Testing with Postman:**

// ### **1. Get All Roles**
// ```
// Method: GET
// URL: http://localhost:8000/api/roles
// Headers:
//   Authorization: Bearer {your_token}
//   Accept: application/json
// ```

// ### **2. Get Single Role**
// ```
// Method: GET
// URL: http://localhost:8000/api/roles/1
// Headers:
//   Authorization: Bearer {your_token}
//   Accept: application/json
// ```

// ### **3. Create Role**
// ```
// Method: POST
// URL: http://localhost:8000/api/roles
// Headers:
//   Authorization: Bearer {your_token}
//   Content-Type: application/json
//   Accept: application/json
// Body (JSON):
// {
//   "role_name": "Store Manager",
//   "description": "Manages store operations",
//   "is_active": true,
//   "permissions": [1, 2, 3, 10, 11]
// }
// ```

// ### **4. Update Role**
// ```
// Method: PUT
// URL: http://localhost:8000/api/roles/1
// Headers:
//   Authorization: Bearer {your_token}
//   Content-Type: application/json
//   Accept: application/json
// Body (JSON):
// {
//   "role_name": "Senior Store Manager",
//   "description": "Updated description",
//   "is_active": true,
//   "permissions": [1, 2, 3, 4, 10, 11, 12]
// }
// ```

// ### **5. Delete Role**
// ```
// Method: DELETE
// URL: http://localhost:8000/api/roles/1
// Headers:
//   Authorization: Bearer {your_token}
//   Accept: application/json
// ```

// ### **6. Assign Permissions to Role**
// ```
// Method: POST
// URL: http://localhost:8000/api/roles/2/assign-permissions
// Headers:
//   Authorization: Bearer {your_token}
//   Content-Type: application/json
//   Accept: application/json
// Body (JSON):
// {
//   "permissions": [10, 11, 12, 15, 16, 17]
// }
// ```

// ### **7. Get All Permissions**
// ```
// Method: GET
// URL: http://localhost:8000/api/permissions
// Headers:
//   Authorization: Bearer {your_token}
//   Accept: application/json
// ```

// ### **8. Get Permissions by Module**
// ```
// Method: GET
// URL: http://localhost:8000/api/permissions/module/Product Management
// Headers:
//   Authorization: Bearer {your_token}
//   Accept: application/json