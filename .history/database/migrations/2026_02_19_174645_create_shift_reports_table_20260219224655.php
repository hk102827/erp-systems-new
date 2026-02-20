<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('shift_number')->unique();
            
            // Shift timing
            $table->timestamp('shift_start');
            $table->timestamp('shift_end')->nullable();
            
            // Cash summary
            $table->decimal('opening_cash', 10, 3);
            $table->decimal('closing_cash', 10, 3)->nullable();
            $table->decimal('expected_cash', 10, 3)->nullable();
            $table->decimal('cash_difference', 10, 3)->nullable();
            
            // Sales summary
            $table->integer('total_transactions')->default(0);
            $table->decimal('total_sales', 10, 3)->default(0);
            $table->decimal('total_discounts', 10, 3)->default(0);
            $table->decimal('total_returns', 10, 3)->default(0);
            $table->decimal('net_sales', 10, 3)->default(0);
            
            // Payment breakdown
            $table->decimal('cash_sales', 10, 3)->default(0);
            $table->decimal('card_sales', 10, 3)->default(0);
            $table->decimal('knet_sales', 10, 3)->default(0);
            $table->decimal('mobile_payment_sales', 10, 3)->default(0);
            
            // Cash movements
            $table->decimal('cash_in', 10, 3)->default(0);
            $table->decimal('cash_out', 10, 3)->default(0);
            
            $table->text('notes')->nullable();
            $table->enum('status', ['Open', 'Closed'])->default('Open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_reports');
    }
};