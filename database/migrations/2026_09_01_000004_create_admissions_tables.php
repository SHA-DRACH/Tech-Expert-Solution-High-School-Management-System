<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('application_number');
            $table->string('status')->default('submitted')->index();
            $table->string('student_first_name'); $table->string('student_middle_name')->nullable(); $table->string('student_last_name');
            $table->string('gender', 20)->nullable(); $table->date('date_of_birth')->nullable(); $table->string('place_of_birth')->nullable(); $table->string('nationality')->nullable();
            $table->string('previous_school')->nullable(); $table->string('previous_class')->nullable(); $table->string('intended_class')->nullable(); $table->string('academic_year')->nullable();
            $table->string('guardian_name'); $table->string('guardian_relationship')->nullable(); $table->string('guardian_phone'); $table->string('guardian_email')->nullable(); $table->text('guardian_address')->nullable(); $table->string('guardian_occupation')->nullable(); $table->string('emergency_contact')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'application_number']);
            $table->index(['school_id', 'student_last_name']);
        });
        Schema::create('admission_documents', function (Blueprint $table) {
            $table->id(); $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->string('document_type'); $table->string('original_name'); $table->string('path'); $table->string('status')->default('pending'); $table->text('review_note')->nullable(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('admission_documents'); Schema::dropIfExists('admissions'); }
};
