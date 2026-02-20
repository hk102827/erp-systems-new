<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('cash_register_id')->nullable()->constrained('cash_registers')->onDelete('set null');
            $table->foreignId('cashier_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('sales_staff_id')->nullable()->constrained('users')->onDelete('set null'); // ✅ NEW
            $table->foreignId('customer_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Sale amounts
            $table->decimal('subtotal', 10, 3);
            $table->decimal('discount_amount', 10, 3)->default(0);
            $table->decimal('tax_amount', 10, 3)->default(0);
            $table->decimal('total_amount', 10, 3);
            $table->decimal('coupon_discount', 10, 3)->default(0); // ✅ NEW
            $table->string('coupon_code')->nullable(); // ✅ NEW
            
            // Payment
            $table->enum('payment_method', ['Cash', 'Card', 'K-Net', 'Mobile Payment', 'Mixed'])->default('Cash');
            $table->decimal('cash_received', 10, 3)->nullable();
            $table->decimal('change_given', 10, 3)->nullable();
            $table->decimal('card_amount', 10, 3)->nullable();
            $table->string('card_reference')->nullable();
            
            // Additional features
            $table->boolean('is_gift')->default(false); // ✅ NEW - "This is a Gift" option
            $table->boolean('is_employee_purchase')->default(false); // ✅ NEW
            $table->decimal('employee_discount_amount', 10, 3)->default(0); // ✅ NEW
            
            // Status
            $table->enum('status', ['Completed', 'Refunded', 'Partially Refunded', 'Cancelled'])->default('Completed');
            $table->text('notes')->nullable();
            $table->timestamp('sale_date');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};