<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('document_type', [
                'Contract',
                'ID Copy',
                'Passport',
                'Work Permit',
                'Visa',
                'Certificate',
                'Other'
            ]);
            $table->string('document_name');
            $table->string('document_path');
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable(); // Changed this line
            $table->timestamps();
            $table->softDeletes();
            
            // Add foreign key separately
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};