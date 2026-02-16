<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damaged_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->integer('quantity');
            $table->enum('damage_type', [
                'Broken',
                'Expired',
                'Water Damage',
                'Manufacturing Defect',
                'Other'
            ]);
            $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
            $table->date('reported_date');
            $table->enum('status', [
                'Pending',
                'Sent to Repair',
                'Repaired',
                'Discarded'
            ])->default('Pending');
            $table->enum('repair_decision', [
                'Pending',
                'Repairable',
                'Not Repairable'
            ])->default('Pending');
            $table->text('repair_notes')->nullable();
            $table->decimal('expense_amount', 10, 3)->nullable(); // If discarded
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damaged_items');
    }
};