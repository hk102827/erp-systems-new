<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LeaveType;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $leaveTypes = [
            [
                'leave_type_name' => 'Sick Leave',
                'max_days_per_year' => 15,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
                'description' => 'Leave for medical reasons',
            ],
            [
                'leave_type_name' => 'Married Leave',
                'max_days_per_year' => 3,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
                'description' => 'Leave for marriage',
            ],
            [
                'leave_type_name' => 'Umrah',
                'max_days_per_year' => 10,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
                'description' => 'Leave for religious pilgrimage',
            ],
            [
                'leave_type_name' => 'Bereavement',
                'max_days_per_year' => 5,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
                'description' => 'Leave for death in family',
            ],
            [
                'leave_type_name' => 'Maternity Leave',
                'max_days_per_year' => 90,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
                'description' => 'Leave for maternity',
            ],
            [
                'leave_type_name' => 'Paternity Leave',
                'max_days_per_year' => 3,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
                'description' => 'Leave for paternity',
            ],
            [
                'leave_type_name' => 'Public Holiday',
                'max_days_per_year' => 30,
                'is_paid' => true,
                'requires_approval' => false,
                'is_active' => true,
                'description' => 'Public holidays',
            ],
            [
                'leave_type_name' => 'Emergency Leave',
                'max_days_per_year' => 5,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
                'description' => 'Emergency situations',
            ],
            [
                'leave_type_name' => 'Annual Leave',
                'max_days_per_year' => 30,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
                'description' => 'Annual vacation',
            ],
        ];

        foreach ($leaveTypes as $leaveType) {
            LeaveType::create($leaveType);
        }

        $this->command->info('Leave types created successfully!');
    }
}