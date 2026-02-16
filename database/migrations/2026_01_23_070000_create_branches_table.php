<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('branch_name');
            $table->enum('branch_type', [
                'Warehouse',
                'Retail',
                'B2B',
                'E-Commerce',
                'Repair',
                'Discard',
                'Marketing',
                'Expo'
            ]);
            $table->boolean('has_pos')->default(false);
            $table->boolean('has_inventory')->default(true);
            $table->boolean('has_cash_bank')->default(false);
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_temporary')->default(false); // For Expo
            $table->date('opening_date')->nullable();
            $table->date('closing_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};