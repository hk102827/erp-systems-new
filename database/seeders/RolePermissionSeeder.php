<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create Permissions
        $permissions = [
            // User Management
            ['permission_name' => 'view_users', 'module_name' => 'User Management', 'description' => 'View users list'],
            ['permission_name' => 'create_user', 'module_name' => 'User Management', 'description' => 'Create new user'],
            ['permission_name' => 'edit_user', 'module_name' => 'User Management', 'description' => 'Edit user details'],
            ['permission_name' => 'delete_user', 'module_name' => 'User Management', 'description' => 'Delete user'],
            
            // Role Management
            ['permission_name' => 'view_roles', 'module_name' => 'Role Management', 'description' => 'View roles list'],
            ['permission_name' => 'create_role', 'module_name' => 'Role Management', 'description' => 'Create new role'],
            ['permission_name' => 'edit_role', 'module_name' => 'Role Management', 'description' => 'Edit role'],
            ['permission_name' => 'delete_role', 'module_name' => 'Role Management', 'description' => 'Delete role'],
            ['permission_name' => 'assign_permissions', 'module_name' => 'Role Management', 'description' => 'Assign permissions to role'],
            
            // Product Management
            ['permission_name' => 'view_products', 'module_name' => 'Product Management', 'description' => 'View products list'],
            ['permission_name' => 'create_product', 'module_name' => 'Product Management', 'description' => 'Create new product'],
            ['permission_name' => 'edit_product', 'module_name' => 'Product Management', 'description' => 'Edit product'],
            ['permission_name' => 'delete_product', 'module_name' => 'Product Management', 'description' => 'Delete product'],
            ['permission_name' => 'view_inventory', 'module_name' => 'Product Management', 'description' => 'View inventory across branches'],
            
            // POS
            ['permission_name' => 'access_pos', 'module_name' => 'POS', 'description' => 'Access POS system'],
            ['permission_name' => 'process_sale', 'module_name' => 'POS', 'description' => 'Process sales transaction'],
            ['permission_name' => 'apply_discount', 'module_name' => 'POS', 'description' => 'Apply discount on sale'],
            ['permission_name' => 'process_return', 'module_name' => 'POS', 'description' => 'Process returns'],
            
            // Transfer Management
            ['permission_name' => 'request_transfer', 'module_name' => 'Transfer Management', 'description' => 'Request stock transfer'],
            ['permission_name' => 'approve_transfer', 'module_name' => 'Transfer Management', 'description' => 'Approve transfer request'],
            
            // Purchase Management
            ['permission_name' => 'view_purchases', 'module_name' => 'Purchase Management', 'description' => 'View purchase orders'],
            ['permission_name' => 'create_purchase', 'module_name' => 'Purchase Management', 'description' => 'Create purchase order'],
            ['permission_name' => 'approve_purchase', 'module_name' => 'Purchase Management', 'description' => 'Approve purchase order'],
            
            // Accounting
            ['permission_name' => 'view_reports', 'module_name' => 'Accounting', 'description' => 'View financial reports'],
            ['permission_name' => 'create_journal_entry', 'module_name' => 'Accounting', 'description' => 'Create journal entry'],
            ['permission_name' => 'view_balance_sheet', 'module_name' => 'Accounting', 'description' => 'View balance sheet'],
            
            // Branch Management
            ['permission_name' => 'view_branches', 'module_name' => 'Branch Management', 'description' => 'View branches'],
            ['permission_name' => 'create_branch', 'module_name' => 'Branch Management', 'description' => 'Create new branch'],
            ['permission_name' => 'edit_branch', 'module_name' => 'Branch Management', 'description' => 'Edit branch'],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

        // Create Roles
        $superAdmin = Role::create([
            'role_name' => 'Super Admin',
            'description' => 'Full system access',
            'is_active' => true,
        ]);

        $branchManager = Role::create([
            'role_name' => 'Branch Manager',
            'description' => 'Manages branch operations',
            'is_active' => true,
        ]);

        $cashier = Role::create([
            'role_name' => 'Cashier',
            'description' => 'POS operations',
            'is_active' => true,
        ]);

        $salesStaff = Role::create([
            'role_name' => 'Sales Staff',
            'description' => 'Sales and customer service',
            'is_active' => true,
        ]);

        $warehouseOperator = Role::create([
            'role_name' => 'Warehouse Operator',
            'description' => 'Warehouse and inventory management',
            'is_active' => true,
        ]);

        $accountant = Role::create([
            'role_name' => 'Accountant',
            'description' => 'Financial management',
            'is_active' => true,
        ]);

        // Assign all permissions to Super Admin
        $superAdmin->permissions()->attach(Permission::all());

        // Assign specific permissions to Branch Manager
        $branchManager->permissions()->attach(Permission::whereIn('permission_name', [
            'view_products',
            'view_inventory',
            'access_pos',
            'process_sale',
            'apply_discount',
            'process_return',
            'request_transfer',
            'approve_transfer',
            'view_reports',
        ])->pluck('id'));

        // Assign specific permissions to Cashier
        $cashier->permissions()->attach(Permission::whereIn('permission_name', [
            'view_products',
            'view_inventory',
            'access_pos',
            'process_sale',
            'apply_discount',
            'process_return',
        ])->pluck('id'));

        // Assign specific permissions to Sales Staff
        $salesStaff->permissions()->attach(Permission::whereIn('permission_name', [
            'view_products',
            'view_inventory',
            'access_pos',
            'process_sale',
        ])->pluck('id'));

        // Assign specific permissions to Warehouse Operator
        $warehouseOperator->permissions()->attach(Permission::whereIn('permission_name', [
            'view_products',
            'view_inventory',
            'request_transfer',
            'approve_transfer',
        ])->pluck('id'));

        // Assign specific permissions to Accountant
        $accountant->permissions()->attach(Permission::whereIn('permission_name', [
            'view_reports',
            'create_journal_entry',
            'view_balance_sheet',
            'view_purchases',
        ])->pluck('id'));
    }
}