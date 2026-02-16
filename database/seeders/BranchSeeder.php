<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            // Warehouses
            [
                'branch_name' => 'Warehouse - Qurain',
                'branch_type' => 'Warehouse',
                'has_pos' => false,
                'has_inventory' => true,
                'has_cash_bank' => true,
                'address' => 'Qurain Industrial Area, Kuwait',
                'phone' => '+965 11111111',
                'email' => 'warehouse.qurain@example.com',
                'is_active' => true,
                'is_temporary' => false,
            ],
            [
                'branch_name' => 'Warehouse - Mangaf',
                'branch_type' => 'Warehouse',
                'has_pos' => false,
                'has_inventory' => true,
                'has_cash_bank' => false,
                'address' => 'Mangaf Area, Kuwait',
                'phone' => '+965 22222222',
                'email' => 'warehouse.mangaf@example.com',
                'is_active' => true,
                'is_temporary' => false,
            ],

            // Retail Branches
            [
                'branch_name' => 'Qurain Branch',
                'branch_type' => 'Retail',
                'has_pos' => true,
                'has_inventory' => true,
                'has_cash_bank' => true,
                'address' => 'Qurain, Kuwait',
                'phone' => '+965 33333333',
                'email' => 'qurain@example.com',
                'is_active' => true,
                'is_temporary' => false,
            ],
            [
                'branch_name' => 'Kuwait City Branch',
                'branch_type' => 'Retail',
                'has_pos' => true,
                'has_inventory' => true,
                'has_cash_bank' => true,
                'address' => 'Kuwait City, Kuwait',
                'phone' => '+965 44444444',
                'email' => 'kuwaitcity@example.com',
                'is_active' => true,
                'is_temporary' => false,
            ],
            [
                'branch_name' => 'Ardiya Branch',
                'branch_type' => 'Retail',
                'has_pos' => true,
                'has_inventory' => true,
                'has_cash_bank' => true,
                'address' => 'Ardiya, Kuwait',
                'phone' => '+965 55555555',
                'email' => 'ardiya@example.com',
                'is_active' => true,
                'is_temporary' => false,
            ],
            [
                'branch_name' => 'Army Market Branch',
                'branch_type' => 'Retail',
                'has_pos' => true,
                'has_inventory' => true,
                'has_cash_bank' => true,
                'address' => 'Army Market, Kuwait',
                'phone' => '+965 66666666',
                'email' => 'armymarket@example.com',
                'is_active' => true,
                'is_temporary' => false,
            ],
            [
                'branch_name' => 'Mangef Branch',
                'branch_type' => 'Retail',
                'has_pos' => true,
                'has_inventory' => true,
                'has_cash_bank' => true,
                'address' => 'Mangef, Kuwait',
                'phone' => '+965 77777777',
                'email' => 'mangef@example.com',
                'is_active' => true,
                'is_temporary' => false,
            ],

            // Special Branches
            [
                'branch_name' => 'E-Commerce Branch',
                'branch_type' => 'E-Commerce',
                'has_pos' => false,
                'has_inventory' => true,
                'has_cash_bank' => true,
                'address' => 'Online',
                'phone' => '+965 88888888',
                'email' => 'ecommerce@example.com',
                'is_active' => true,
                'is_temporary' => false,
            ],
            [
                'branch_name' => 'Repair Branch',
                'branch_type' => 'Repair',
                'has_pos' => false,
                'has_inventory' => true,
                'has_cash_bank' => false,
                'address' => 'Repair Center, Kuwait',
                'phone' => '+965 99999999',
                'email' => 'repair@example.com',
                'is_active' => true,
                'is_temporary' => false,
            ],
            [
                'branch_name' => 'Discard Branch',
                'branch_type' => 'Discard',
                'has_pos' => false,
                'has_inventory' => true,
                'has_cash_bank' => false,
                'address' => 'Discard Center, Kuwait',
                'phone' => '+965 10101010',
                'email' => 'discard@example.com',
                'is_active' => true,
                'is_temporary' => false,
            ],
            [
                'branch_name' => 'Marketing Branch',
                'branch_type' => 'Marketing',
                'has_pos' => false,
                'has_inventory' => true,
                'has_cash_bank' => false,
                'address' => 'Marketing Department, Kuwait',
                'phone' => '+965 20202020',
                'email' => 'marketing@example.com',
                'is_active' => true,
                'is_temporary' => false,
            ],
            [
                'branch_name' => 'B2B Branch',
                'branch_type' => 'B2B',
                'has_pos' => false,
                'has_inventory' => true,
                'has_cash_bank' => true,
                'address' => 'B2B Sales Office, Kuwait',
                'phone' => '+965 30303030',
                'email' => 'b2b@example.com',
                'is_active' => true,
                'is_temporary' => false,
            ],
            [
                'branch_name' => 'Expo Branch',
                'branch_type' => 'Expo',
                'has_pos' => true,
                'has_inventory' => true,
                'has_cash_bank' => true,
                'address' => 'Exhibition Center, Kuwait',
                'phone' => '+965 40404040',
                'email' => 'expo@example.com',
                'is_active' => true,
                'is_temporary' => true,
                'opening_date' => now(),
                'closing_date' => now()->addMonths(3),
            ],
        ];

        foreach ($branches as $branch) {
            Branch::create($branch);
        }

        $this->command->info('Branches created successfully!');
    }
}