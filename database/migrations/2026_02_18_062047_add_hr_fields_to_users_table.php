<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Basic HR Info
            $table->string('national_id')->nullable()->after('employee_id');
            $table->date('date_of_birth')->nullable()->after('national_id');
            $table->enum('gender', ['Male', 'Female'])->nullable()->after('date_of_birth');
            $table->enum('marital_status', ['Single', 'Married', 'Divorced', 'Widowed'])->nullable()->after('gender');
            $table->date('joining_date')->nullable()->after('marital_status');
            $table->string('job_title')->nullable()->after('joining_date');
            $table->string('department')->nullable()->after('job_title');
            
            // Salary Info
            $table->decimal('basic_salary', 10, 3)->default(0)->after('department');
            
            // Allowances
            $table->decimal('transportation_allowance', 10, 3)->default(0)->after('basic_salary');
            $table->decimal('housing_allowance', 10, 3)->default(0)->after('transportation_allowance');
            $table->decimal('communication_allowance', 10, 3)->default(0)->after('housing_allowance');
            $table->decimal('meal_allowance', 10, 3)->default(0)->after('communication_allowance');
            $table->decimal('accommodation_allowance', 10, 3)->default(0)->after('meal_allowance');
            
            // Contact Info
            $table->text('emergency_contact_name')->nullable()->after('accommodation_allowance');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->text('address')->nullable()->after('emergency_contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'national_id',
                'date_of_birth',
                'gender',
                'marital_status',
                'joining_date',
                'job_title',
                'department',
                'basic_salary',
                'transportation_allowance',
                'housing_allowance',
                'communication_allowance',
                'meal_allowance',
                'accommodation_allowance',
                'emergency_contact_name',
                'emergency_contact_phone',
                'address',
            ]);
        });
    }
};