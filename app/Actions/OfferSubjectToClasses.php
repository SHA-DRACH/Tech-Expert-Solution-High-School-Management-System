<?php

namespace App\Actions;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

/**
 * Put a subject on the list of the students already in a class.
 *
 * A student's subjects are recorded when they are enrolled, so that an
 * elective can be taken by only part of a class. That left a gap: a subject
 * placed under a class *after* its students were enrolled reached the class
 * but none of the children in it - it was missing from every grade sheet and
 * report card, and no teacher could enter a mark for it.
 *
 * Enrolling a student already takes every subject the class offers; this does
 * the same for the students who were there first. It is additive and limited
 * to the current academic year: a subject added this year says nothing about
 * what a child studied last year.
 */
class OfferSubjectToClasses
{
    /** @param array<int, int> $classIds  classes the subject was just added to */
    public function handle(Subject $subject, array $classIds): int
    {
        $year = AcademicYear::active();

        if ($year === null || $classIds === []) {
            return 0;
        }

        $studentIds = Enrollment::where('academic_year_id', $year->id)
            ->whereIn('school_class_id', $classIds)
            ->pluck('student_id')
            ->unique();

        $already = DB::table('student_subject')
            ->where('subject_id', $subject->id)
            ->where('academic_year_id', $year->id)
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id');

        $now = now();

        $rows = $studentIds->diff($already)->map(fn (int $studentId) => [
            'school_id' => $subject->school_id,
            'student_id' => $studentId,
            'subject_id' => $subject->id,
            'academic_year_id' => $year->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('student_subject')->insert($chunk);
        }

        return count($rows);
    }
}
