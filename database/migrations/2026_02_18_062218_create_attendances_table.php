<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->date('attendance_date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->integer('total_hours')->default(0); // in minutes
            $table->enum('status', [
                'Present',
                'Absent',
                'Late',
                'Half Day',
                'On Leave'
            ])->default('Present');
            $table->string('device_id')->nullable(); // Attendance machine ID
            $table->text('notes')->nullable();
            $table->timestamps();

            // Prevent duplicate entries for same user on same date
            $table->unique(['user_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};