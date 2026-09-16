<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repair students who missed a subject added to their class after they enrolled.
 *
 * Until now, placing a subject under a class reached the class but not the
 * students already in it, so the subject was missing from their grade sheets
 * and report cards and no teacher could mark them in it.
 *
 * Only the gap that bug caused is filled. A student gets a subject when:
 *
 *   - they are enrolled in the class in a current academic year;
 *   - they already have subjects recorded for that year (a student with none
 *     is already treated as taking everything, so needs nothing); and
 *   - the subject was placed under the class *after* their subjects were
 *     recorded.
 *
 * A subject that was offered when the student enrolled and is still absent was
 * left out on purpose - an elective they do not take - and is not touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $missing = DB::table('enrollments')
            ->join('academic_years', 'academic_years.id', '=', 'enrollments.academic_year_id')
            ->join('class_subject', 'class_subject.school_class_id', '=', 'enrollments.school_class_id')
            ->where('academic_years.is_current', true)
            ->whereNotExists(fn ($q) => $q->from('student_subject')
                ->whereColumn('student_subject.student_id', 'enrollments.student_id')
                ->whereColumn('student_subject.subject_id', 'class_subject.subject_id')
                ->whereColumn('student_subject.academic_year_id', 'enrollments.academic_year_id'))
            ->whereExists(fn ($q) => $q->from('student_subject as recorded')
                ->whereColumn('recorded.student_id', 'enrollments.student_id')
                ->whereColumn('recorded.academic_year_id', 'enrollments.academic_year_id')
                ->whereColumn('recorded.created_at', '<', 'class_subject.created_at'))
            ->whereNotExists(fn ($q) => $q->from('student_subject as later')
                ->whereColumn('later.student_id', 'enrollments.student_id')
                ->whereColumn('later.academic_year_id', 'enrollments.academic_year_id')
                ->whereColumn('later.created_at', '>=', 'class_subject.created_at'))
            ->select('enrollments.school_id', 'enrollments.student_id', 'class_subject.subject_id', 'enrollments.academic_year_id')
            ->distinct()
            ->get();

        foreach ($missing->chunk(500) as $chunk) {
            DB::table('student_subject')->insertOrIgnore($chunk->map(fn ($row) => [
                'school_id' => $row->school_id,
                'student_id' => $row->student_id,
                'subject_id' => $row->subject_id,
                'academic_year_id' => $row->academic_year_id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }
    }

    public function down(): void
    {
        // Data repair; nothing to undo safely.
    }
};
