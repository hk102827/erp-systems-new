<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('payroll_month'); // Format: YYYY-MM
            $table->decimal('basic_salary', 10, 3);
            $table->decimal('total_allowances', 10, 3)->default(0);
            $table->decimal('total_bonuses', 10, 3)->default(0);
            $table->decimal('total_deductions', 10, 3)->default(0);
            $table->decimal('net_salary', 10, 3);
            $table->integer('working_days');
            $table->integer('present_days');
            $table->integer('absent_days');
            $table->integer('leave_days');
            $table->enum('status', [
                'Draft',
                'Approved',
                'Paid'
            ])->default('Draft');
            $table->date('payment_date')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Prevent duplicate payroll for same user in same month
            $table->unique(['user_id', 'payroll_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};php artisan migrate