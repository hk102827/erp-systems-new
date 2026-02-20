<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('coupon_code')->unique();
            $table->string('coupon_name');
            $table->text('description')->nullable();
            
            // Discount type
            $table->enum('discount_type', ['Percentage', 'Fixed Amount'])->default('Percentage');
            $table->decimal('discount_value', 10, 3);
            $table->decimal('max_discount_amount', 10, 3)->nullable(); // Max discount for percentage type
            $table->decimal('min_purchase_amount', 10, 3)->default(0);
            
            // Usage limits
            $table->integer('usage_limit')->nullable(); // Total usage limit
            $table->integer('usage_limit_per_user')->default(1);
            $table->integer('times_used')->default(0);
            
            // Validity
            $table->date('valid_from');
            $table->date('valid_until');
            
            // Applicability
            $table->json('applicable_branches')->nullable(); // Branch IDs
            $table->json('applicable_products')->nullable(); // Product IDs
            $table->json('applicable_categories')->nullable(); // Category IDs
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->enum('channel', ['All', 'POS', 'Website', 'Mobile App'])->default('All');
            
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};