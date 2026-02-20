<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Cashier
            $table->string('register_number')->unique();
            $table->decimal('opening_balance', 10, 3)->default(0);
            $table->decimal('closing_balance', 10, 3)->nullable();
            $table->decimal('expected_balance', 10, 3)->nullable();
            $table->decimal('difference', 10, 3)->nullable(); // Cash over/short
            $table->enum('status', ['Open', 'Closed'])->default('Open');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};