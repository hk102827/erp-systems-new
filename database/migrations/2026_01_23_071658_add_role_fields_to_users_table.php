<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
Schema::table('users', function (Blueprint $table) {
    $table->string('employee_id')->unique()->nullable()->after('id');
    $table->string('phone')->nullable()->after('email');
    $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_login')->nullable();
    $table->softDeletes();
});

    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropForeign(['branch_id']);
            $table->dropColumn([
                'employee_id',
                'phone',
                'role_id',
                'branch_id',
                'is_active',
                'last_login',
                'deleted_at'
            ]);
        });
    }
};