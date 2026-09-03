<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Enrolling a student in a class, and recording the subjects they take.
 *
 * Nothing in the application created an enrolment before this. Not the student
 * form, not admissions — the rows existed because a seeder wrote them, so a
 * student added through the interface stayed "not assigned" for ever: no
 * register, no mark sheet, no report card, no timetable.
 */
class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected SchoolClass $grade;

    protected Section $sectionA;

    protected Section $sectionB;

    protected Subject $maths;

    protected Subject $further;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();
        app(SchoolContext::class)->setSchool($this->school);

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026 / 2027',
            'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_current' => true,
        ]);

        Term::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'name' => 'First Term', 'sequence' => 1,
            'starts_on' => '2026-09-01', 'ends_on' => '2026-12-15', 'is_current' => true,
        ]);

        $this->grade = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 12', 'level' => 12]);

        $this->sectionA = Section::create(['school_id' => $this->school->id, 'school_class_id' => $this->grade->id, 'name' => 'A']);
        $this->sectionB = Section::create(['school_id' => $this->school->id, 'school_class_id' => $this->grade->id, 'name' => 'B']);

        $this->maths = Subject::create(['school_id' => $this->school->id, 'name' => 'Mathematics', 'code' => 'MTH']);
        $this->further = Subject::create(['school_id' => $this->school->id, 'name' => 'Further Mathematics', 'code' => 'FMT']);

        $this->grade->subjects()->attach(
            [$this->maths->id, $this->further->id],
            ['school_id' => $this->school->id]
        );
    }

    protected function registrar()
    {
        return $this->userFor($this->school, [
            'students.view', 'students.create', 'students.update', 'grades.enter', 'grades.approve',
        ]);
    }

    protected function student(string $number = 'S-1'): Student
    {
        return Student::create([
            'school_id' => $this->school->id, 'student_number' => $number,
            'first_name' => 'Ada', 'last_name' => 'Doe', 'status' => 'active',
        ]);
    }

    /* ------------------------------------------------------- placing them */

    public function test_a_student_is_placed_in_a_class_when_created(): void
    {
        $this->actingAs($this->registrar())
            ->post(route('students.store'), [
                'first_name' => 'Ada',
                'last_name' => 'Doe',
                'section_id' => $this->sectionA->id,
            ])
            ->assertRedirect();

        $student = Student::firstOrFail();

        $enrollment = Enrollment::where('student_id', $student->id)->firstOrFail();

        $this->assertSame($this->sectionA->id, $enrollment->section_id);
        $this->assertSame($this->grade->id, $enrollment->school_class_id);
        $this->assertSame($this->year->id, $enrollment->academic_year_id);

        // And down for everything the class offers.
        $this->assertEqualsCanonicalizing(
            [$this->maths->id, $this->further->id],
            $student->subjectsFor($this->year)->pluck('subjects.id')->all(),
        );
    }

    public function test_a_student_can_be_created_without_a_class(): void
    {
        // Allowed — a school may add the student before deciding the class.
        $this->actingAs($this->registrar())
            ->post(route('students.store'), ['first_name' => 'Ada', 'last_name' => 'Doe'])
            ->assertRedirect();

        $this->assertSame(0, Enrollment::count());
    }

    public function test_an_unplaced_student_is_told_so_on_their_record(): void
    {
        $student = $this->student();

        /*
         | Said plainly rather than left as a blank field. A student with no
         | placement cannot be marked, registered or reported on, and nothing
         | on screen used to explain why.
         */
        $this->actingAs($this->registrar())
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('not in a class yet')
            ->assertSee('will not appear on any register');
    }

    public function test_a_student_can_be_enrolled_from_their_record(): void
    {
        $student = $this->student();

        $this->actingAs($this->registrar())
            ->post(route('students.enrol', $student), [
                'academic_year_id' => $this->year->id,
                'section_id' => $this->sectionA->id,
                'roll_number' => '07',
            ])
            ->assertRedirect();

        $enrollment = Enrollment::where('student_id', $student->id)->firstOrFail();

        $this->assertSame($this->sectionA->id, $enrollment->section_id);
        $this->assertSame('07', $enrollment->roll_number);
        $this->assertSame(2, $student->subjectsFor($this->year)->count());
    }

    public function test_moving_a_student_replaces_the_placement_rather_than_adding_one(): void
    {
        $student = $this->student();
        $registrar = $this->registrar();

        $this->actingAs($registrar)->post(route('students.enrol', $student), [
            'academic_year_id' => $this->year->id,
            'section_id' => $this->sectionA->id,
        ]);

        $this->actingAs($registrar)->post(route('students.enrol', $student), [
            'academic_year_id' => $this->year->id,
            'section_id' => $this->sectionB->id,
        ]);

        /*
         | One placement per student per year. Two would make "which class is
         | this child in?" unanswerable, and attendance, marks and report cards
         | all ask it.
         */
        $this->assertSame(1, Enrollment::where('student_id', $student->id)->count());
        $this->assertSame($this->sectionB->id, Enrollment::where('student_id', $student->id)->value('section_id'));
    }

    public function test_a_class_from_another_school_cannot_be_used(): void
    {
        $other = $this->createSchool(['name' => 'Another School']);
        $otherClass = SchoolClass::create(['school_id' => $other->id, 'name' => 'Their Grade', 'level' => 9]);
        $otherSection = Section::create(['school_id' => $other->id, 'school_class_id' => $otherClass->id, 'name' => 'A']);

        $student = $this->student();

        $this->actingAs($this->registrar())
            ->post(route('students.enrol', $student), [
                'academic_year_id' => $this->year->id,
                'section_id' => $otherSection->id,
            ])
            ->assertNotFound();

        $this->assertSame(0, Enrollment::count());
    }

    /* -------------------------------------------------------- subjects */

    public function test_a_subject_can_be_deselected_for_one_student(): void
    {
        $student = $this->student();
        $registrar = $this->registrar();

        $this->actingAs($registrar)->post(route('students.enrol', $student), [
            'academic_year_id' => $this->year->id,
            'section_id' => $this->sectionA->id,
        ]);

        // This student does not take Further Mathematics.
        $this->actingAs($registrar)->put(route('students.subjects.update', $student), [
            'academic_year_id' => $this->year->id,
            'subject_ids' => [$this->maths->id],
        ])->assertRedirect();

        $this->assertSame(
            [$this->maths->id],
            $student->subjectsFor($this->year)->pluck('subjects.id')->all(),
        );
    }

    public function test_a_subject_the_class_does_not_offer_cannot_be_attached(): void
    {
        $student = $this->student();
        $registrar = $this->registrar();

        $latin = Subject::create(['school_id' => $this->school->id, 'name' => 'Latin', 'code' => 'LAT']);

        $this->actingAs($registrar)->post(route('students.enrol', $student), [
            'academic_year_id' => $this->year->id,
            'section_id' => $this->sectionA->id,
        ]);

        $this->actingAs($registrar)->put(route('students.subjects.update', $student), [
            'academic_year_id' => $this->year->id,
            'subject_ids' => [$this->maths->id, $latin->id],
        ]);

        // A student cannot take a subject their class does not run.
        $this->assertSame(
            [$this->maths->id],
            $student->subjectsFor($this->year)->pluck('subjects.id')->all(),
        );
    }

    public function test_subjects_cannot_be_set_before_a_class_is_chosen(): void
    {
        $student = $this->student();

        $this->actingAs($this->registrar())
            ->put(route('students.subjects.update', $student), [
                'academic_year_id' => $this->year->id,
                'subject_ids' => [$this->maths->id],
            ])
            ->assertSessionHasErrors('subject_ids');
    }

    /* ------------------------------------------------- what it feeds into */

    public function test_the_mark_sheet_lists_only_students_who_take_the_subject(): void
    {
        $registrar = $this->registrar();

        $takesBoth = $this->student('S-1');
        $mathsOnly = Student::create([
            'school_id' => $this->school->id, 'student_number' => 'S-2',
            'first_name' => 'Ben', 'last_name' => 'Doe', 'status' => 'active',
        ]);

        foreach ([$takesBoth, $mathsOnly] as $student) {
            $this->actingAs($registrar)->post(route('students.enrol', $student), [
                'academic_year_id' => $this->year->id,
                'section_id' => $this->sectionA->id,
            ]);
        }

        $this->actingAs($registrar)->put(route('students.subjects.update', $mathsOnly), [
            'academic_year_id' => $this->year->id,
            'subject_ids' => [$this->maths->id],
        ]);

        Assessment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'term_id' => Term::first()->id, 'section_id' => $this->sectionA->id,
            'subject_id' => $this->further->id, 'title' => 'FM Test', 'type' => 'test', 'max_score' => 100,
        ]);

        $assessment = Assessment::where('subject_id', $this->further->id)->firstOrFail();

        /*
         | Asserted on each student's own mark box, not on their name. A name
         | can appear in a flash message left over from the previous request -
         | "Subjects updated for Ben Doe" - which made an earlier version of
         | this test fail while the roster was perfectly correct.
         |
         | Listing the whole class against an elective invites a mark being
         | entered for a child who never sat the paper.
         */
        $html = $this->actingAs($registrar)
            ->get(route('marks.index', ['section' => $this->sectionA->id, 'subject' => $this->further->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("marks[{$assessment->id}][{$takesBoth->id}]", $html);
        $this->assertStringNotContainsString("marks[{$assessment->id}][{$mathsOnly->id}]", $html);
    }

    public function test_a_student_with_no_subjects_recorded_still_appears(): void
    {
        /*
         | The safety net. Filtering on the pivot alone would make anyone whose
         | subjects were never recorded — an older record, a CSV import, a row
         | written straight to the database — vanish from every mark sheet in
         | the school, silently and with no error to explain it.
         */
        $student = $this->student();

        Enrollment::create([
            'school_id' => $this->school->id, 'student_id' => $student->id,
            'academic_year_id' => $this->year->id, 'school_class_id' => $this->grade->id,
            'section_id' => $this->sectionA->id, 'status' => 'active',
        ]);

        $this->assertSame(0, DB::table('student_subject')->where('student_id', $student->id)->count());

        Assessment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'term_id' => Term::first()->id, 'section_id' => $this->sectionA->id,
            'subject_id' => $this->maths->id, 'title' => 'Test', 'type' => 'test', 'max_score' => 100,
        ]);

        $this->actingAs($this->registrar())
            ->get(route('marks.index', ['section' => $this->sectionA->id, 'subject' => $this->maths->id]))
            ->assertOk()
            ->assertSee('Ada Doe');
    }

    /* ---------------------------------------------------- removing it */

    public function test_a_placement_holding_marks_cannot_be_removed(): void
    {
        $student = $this->student();
        $registrar = $this->registrar();

        $this->actingAs($registrar)->post(route('students.enrol', $student), [
            'academic_year_id' => $this->year->id,
            'section_id' => $this->sectionA->id,
        ]);

        $assessment = Assessment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'term_id' => Term::first()->id, 'section_id' => $this->sectionA->id,
            'subject_id' => $this->maths->id, 'title' => 'Test', 'type' => 'test', 'max_score' => 100,
        ]);

        AssessmentScore::create([
            'school_id' => $this->school->id, 'assessment_id' => $assessment->id,
            'student_id' => $student->id, 'score' => 70,
        ]);

        // Removing it would leave those marks attached to a class the child was
        // never in.
        $this->actingAs($registrar)
            ->delete(route('students.enrolment.destroy', Enrollment::first()))
            ->assertSessionHasErrors('enrollment');

        $this->assertSame(1, Enrollment::count());
    }

    /* ------------------------------------------------ teacher side of it */

    public function test_a_teacher_is_assigned_to_a_class_and_a_subject(): void
    {
        $teacher = Teacher::create([
            'school_id' => $this->school->id, 'staff_number' => 'T-001',
            'first_name' => 'Grace', 'last_name' => 'Kollie', 'status' => 'active',
        ]);

        $this->actingAs($this->userFor($this->school, ['academics.view', 'academics.manage']))
            ->post(route('assignments.store'), [
                'teacher_id' => $teacher->id,
                'section_id' => [$this->sectionA->id, $this->sectionB->id],
                'subject_id' => [$this->maths->id],
            ])
            ->assertRedirect();

        // The mirror of a student's enrolment: a teacher holds (class, subject)
        // pairs, and that table is what authorises them to enter marks.
        $assignments = TeachingAssignment::where('teacher_id', $teacher->id)->get();

        $this->assertCount(2, $assignments);
        $this->assertEqualsCanonicalizing(
            [$this->sectionA->id, $this->sectionB->id],
            $assignments->pluck('section_id')->all(),
        );
        $this->assertSame([$this->maths->id], $assignments->pluck('subject_id')->unique()->values()->all());
    }

    public function test_enrolling_needs_the_update_permission(): void
    {
        $student = $this->student();

        $this->actingAs($this->userFor($this->school, ['students.view']))
            ->post(route('students.enrol', $student), [
                'academic_year_id' => $this->year->id,
                'section_id' => $this->sectionA->id,
            ])
            ->assertForbidden();
    }
}
