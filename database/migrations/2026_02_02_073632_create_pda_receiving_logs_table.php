<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pda_receiving_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->integer('quantity_received');
            $table->string('purchase_reference')->nullable(); // PO number
            $table->foreignId('received_by')->constrained('users')->onDelete('cascade');
            $table->string('device_id')->nullable(); // PDA device identifier
            $table->date('received_date');
            $table->enum('sync_status', [
                'Online',
                'Offline-Pending',
                'Synced'
            ])->default('Online');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pda_receiving_logs');
    }
};