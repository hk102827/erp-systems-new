<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('from_branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->foreignId('to_branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->enum('movement_type', [
                'Purchase',
                'Transfer',
                'Sale',
                'Return',
                'Damage',
                'Repair',
                'Discard',
                'Adjustment'
            ]);
            $table->integer('quantity');
            $table->string('reference_type')->nullable(); // PO, Invoice, Transfer Request, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('moved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('movement_date')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};