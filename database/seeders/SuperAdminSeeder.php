<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Branch;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create a default branch first (if not exists)
        $branch = Branch::firstOrCreate(
            ['branch_name' => 'Head Office'],
            [
                'branch_type' => 'Retail',
                'has_pos' => true,
                'has_inventory' => true,
                'has_cash_bank' => true,
                'address' => 'Kuwait City',
                'phone' => '+965 12345678',
                'email' => 'headoffice@gmail.com',
                'is_active' => true,
                'is_temporary' => false,
            ]
        );

        // Get Super Admin role
        $superAdminRole = Role::where('role_name', 'Super Admin')->first();

        if (!$superAdminRole) {
            $this->command->error('Super Admin role not found. Please run RolePermissionSeeder first.');
            return;
        }

        // Create Super Admin user
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin@123'),
                'employee_id' => 'EMP' . date('Y') . '0001',
                'phone' => '+965 12345678',
                'role_id' => $superAdminRole->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        );

        $this->command->info('Super Admin created successfully!');
        $this->command->info('Email: admin@gmail.com');
        $this->command->info('Password: admin@123');
    }
}