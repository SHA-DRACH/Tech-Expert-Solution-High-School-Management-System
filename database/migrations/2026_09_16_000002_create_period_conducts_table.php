<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student's conduct for a marking period, as printed on the grade sheet.
 *
 * Its own table because it is not a mark: it has no maximum, is not averaged,
 * and is written by the class sponsor rather than by each subject teacher.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_conducts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->string('conduct', 40);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_conducts');
    }
};
