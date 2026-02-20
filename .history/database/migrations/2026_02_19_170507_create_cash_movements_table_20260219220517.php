<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->onDelete('cascade');
            $table->enum('type', ['Cash In', 'Cash Out', 'Sale', 'Return', 'Opening', 'Closing']);
            $table->decimal('amount', 10, 3);
            $table->text('reason')->nullable();
            $table->foreignId('reference_id')->nullable(); // Sale ID or Return ID
            $table->string('reference_type')->nullable(); // Sale or Return
            $table->foreignId('recorded_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('movement_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};