<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_aging', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->date('last_sale_date')->nullable();
            $table->integer('days_without_sale')->default(0); // Auto-calculated
            $table->integer('quantity_on_hand');
            $table->enum('aging_category', [
                '0-30 days',
                '31-60 days',
                '61-90 days',
                '90+ days'
            ])->default('0-30 days');
            $table->timestamp('updated_at')->useCurrent();

            // Prevent duplicates
            $table->unique(['product_id', 'variant_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_aging');
    }
};