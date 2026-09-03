<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Work handed in by students (spec section 23).
 *
 * Kept separate from `assessment_scores`: a submission is what the student
 * turned in, the score is what the teacher awarded, and a student may submit
 * without having been marked yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('original_name')->nullable();
            $table->timestamp('submitted_at')->nullable();
            // submitted | returned
            $table->string('status', 20)->default('submitted');
            $table->text('teacher_note')->nullable();
            $table->timestamps();

            // One submission per student per piece of work; re-submitting
            // replaces what came before.
            $table->unique(['assessment_id', 'student_id']);
            $table->index(['school_id', 'assessment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
