<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\PeriodGrades;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared fixtures for the period, semester and grade sheet tests: a year, a
 * class with Mathematics, and a teacher who can be assigned to it.
 */
abstract class PeriodGradingTestCase extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected Section $section;

    protected Subject $maths;

    protected Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-16 10:00:00');

        $this->school = $this->createSchool();
        app(SchoolContext::class)->setSchool($this->school);

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026 / 2027',
            'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_current' => true,
        ]);

        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 9', 'level' => 9]);
        $this->section = Section::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => 'A']);
        $this->maths = Subject::create(['school_id' => $this->school->id, 'name' => 'Mathematics', 'code' => 'MTH']);
        $class->subjects()->attach([$this->maths->id], ['school_id' => $this->school->id]);

        $this->teacher = Teacher::create([
            'school_id' => $this->school->id, 'staff_number' => 'T-001',
            'first_name' => 'Grace', 'last_name' => 'Kollie', 'status' => 'active',
        ]);
    }

    /* ------------------------------------------------------------ fixtures */

    protected function admin(): User
    {
        return $this->administratorFor($this->school);
    }

    protected function setUpPeriods(): void
    {
        $this->actingAs($this->admin())->post(route('periods.setup', $this->year))->assertRedirect();
    }

    protected function period(int $number): Term
    {
        return app(PeriodGrades::class)->periods($this->year)->get($number);
    }

    protected function semester(int $number): Semester
    {
        return Semester::where('academic_year_id', $this->year->id)->where('number', $number)->firstOrFail();
    }

    protected function student(string $number, string $first = 'Mary'): Student
    {
        $student = Student::create([
            'school_id' => $this->school->id, 'student_number' => $number,
            'first_name' => $first, 'last_name' => 'Doe', 'status' => 'active',
        ]);

        Enrollment::create([
            'school_id' => $this->school->id, 'student_id' => $student->id,
            'academic_year_id' => $this->year->id, 'school_class_id' => $this->section->school_class_id,
            'section_id' => $this->section->id, 'status' => 'active',
        ]);

        return $student;
    }

    protected function teacherUser(array $extra = []): User
    {
        $user = $this->userFor($this->school, array_merge(['grades.enter', 'grades.export', 'grades.import'], $extra));
        $this->teacher->update(['user_id' => $user->id]);

        TeachingAssignment::firstOrCreate([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'teacher_id' => $this->teacher->id, 'section_id' => $this->section->id, 'subject_id' => $this->maths->id,
        ]);

        return $user->fresh();
    }

    /** @param array<string, array<int, mixed>> $marks */
    protected function save(User $user, string $sheet, array $marks)
    {
        return $this->actingAs($user)->post(route('gradesheet.store', [
            'section' => $this->section->id, 'subject' => $this->maths->id, 'sheet' => $sheet,
        ]), ['marks' => $marks]);
    }

    protected function key(int $period): string
    {
        return 'period:'.$this->period($period)->id;
    }

    /* ------------------------------------------------------------ helper */

    protected function makeApproved(Student $student, string $type, ?Term $term, ?Semester $semester, float $score): void
    {
        $assessment = Assessment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'term_id' => $term?->id, 'semester_id' => $semester?->id,
            'section_id' => $this->section->id, 'subject_id' => $this->maths->id,
            'title' => $type, 'type' => $type, 'max_score' => 100, 'weight' => 100, 'status' => 'approved',
        ]);

        AssessmentScore::create([
            'school_id' => $this->school->id, 'assessment_id' => $assessment->id,
            'student_id' => $student->id, 'score' => $score,
        ]);
    }
}
