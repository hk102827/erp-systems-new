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