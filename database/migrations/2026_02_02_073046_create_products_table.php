<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->string('sku')->unique()->nullable();
            $table->string('barcode')->unique()->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->text('description')->nullable();
            $table->string('unit')->default('piece'); // piece, kg, liter, etc.
            $table->decimal('weight', 10, 2)->nullable();
            $table->string('dimensions')->nullable(); // length x width x height
            $table->string('color')->nullable();
            $table->decimal('cost_price', 10, 3)->default(0); // Factory cost
            $table->decimal('selling_price', 10, 3)->default(0);
            $table->decimal('profit_margin', 10, 2)->default(0); // Auto-calculated
            $table->boolean('has_variants')->default(false);
            $table->integer('low_stock_alert')->default(10); // Reorder point
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};