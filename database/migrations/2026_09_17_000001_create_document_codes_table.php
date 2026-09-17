<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verification codes printed on grade sheets and report cards.
 *
 * Random, not derived from the student number and period: a code anyone could
 * work out would let them read any child's grades. Because it is unguessable,
 * whoever types it in must be holding the paper - so the check can show the
 * grades on record, which is what lets a forged or altered paper be caught.
 *
 * One code per document (student, period or year), reused every time that
 * document is printed, so every copy of the same paper checks the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            // grade-sheet | report-card
            $table->string('type', 20);
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('times_checked')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'student_id', 'academic_year_id', 'term_id'], 'document_codes_document_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_codes');
    }
};
