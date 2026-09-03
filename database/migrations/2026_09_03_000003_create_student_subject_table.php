<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which subjects a student actually takes.
 *
 * Until now this was inferred: a student took whatever `class_subject` attached
 * to their grade, with no way to say otherwise. That is right for core subjects
 * and wrong for everything else — a Grade 12 class may offer Further
 * Mathematics to six of its thirty students, and there was no way to record
 * that, so all thirty appeared on its mark sheet.
 *
 * The row is per academic year, because a student's subjects change between
 * years and last year's record must stay as it was.
 *
 * Existing students are backfilled with every subject their class offers, which
 * is what the software already assumed, so nothing changes for a school until
 * it starts deselecting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // One row per student, subject and year.
            $table->unique(['student_id', 'subject_id', 'academic_year_id'], 'student_subject_year_unique');
            $table->index(['school_id', 'subject_id']);
        });

        /*
         | Backfill from what the system already inferred, so the day this ships
         | every student takes exactly what they took the day before.
         */
        $rows = DB::table('enrollments')
            ->join('class_subject', 'class_subject.school_class_id', '=', 'enrollments.school_class_id')
            ->select(
                'enrollments.school_id',
                'enrollments.student_id',
                'class_subject.subject_id',
                'enrollments.academic_year_id',
            )
            ->distinct()
            ->get();

        foreach ($rows->chunk(500) as $chunk) {
            DB::table('student_subject')->insert(
                $chunk->map(fn ($row) => [
                    'school_id' => $row->school_id,
                    'student_id' => $row->student_id,
                    'subject_id' => $row->subject_id,
                    'academic_year_id' => $row->academic_year_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all()
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subject');
    }
};
