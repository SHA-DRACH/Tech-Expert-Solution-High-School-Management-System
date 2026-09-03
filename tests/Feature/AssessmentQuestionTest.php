<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentPermission;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The question a teacher sets, and the paper they attach to it.
 *
 * An assessment recorded a title, a mark and a deadline, and nowhere to write
 * what the students were actually being asked to do — so a parent asking "what
 * has she been set?" had no answer beyond its title.
 *
 * The uploaded paper lives on the private disk. Most of these tests are about
 * who may fetch it: a question paper on a guessable public URL before the exam
 * has been sat is a different kind of problem entirely.
 */
class AssessmentQuestionTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected Term $term;

    protected Section $section;

    protected Subject $maths;

    protected Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->school = $this->createSchool();
        app(SchoolContext::class)->setSchool($this->school);

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026 / 2027',
            'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_current' => true,
        ]);

        $this->term = Term::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'name' => 'First Term', 'sequence' => 1,
            'starts_on' => '2026-09-01', 'ends_on' => '2026-12-15', 'is_current' => true,
        ]);

        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 9', 'level' => 9]);
        $this->section = Section::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => 'A']);

        $this->maths = Subject::create(['school_id' => $this->school->id, 'name' => 'Mathematics', 'code' => 'MTH']);
        $class->subjects()->attach($this->maths->id, ['school_id' => $this->school->id]);

        $this->teacher = Teacher::create([
            'school_id' => $this->school->id, 'staff_number' => 'T-001',
            'first_name' => 'Grace', 'last_name' => 'Kollie', 'status' => 'active',
        ]);
    }

    protected ?User $teacherUser = null;

    /**
     * Memoised: a second call would try to create the same teaching assignment
     * again and trip its unique constraint, which is the constraint doing its
     * job rather than anything wrong with the code under test.
     */
    protected function teacherUser(): User
    {
        if ($this->teacherUser) {
            return $this->teacherUser;
        }

        $user = $this->userFor($this->school, ['grades.enter', 'exams.view']);

        $this->teacher->update(['user_id' => $user->id]);

        TeachingAssignment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'teacher_id' => $this->teacher->id, 'section_id' => $this->section->id,
            'subject_id' => $this->maths->id,
        ]);

        return $this->teacherUser = $user;
    }

    protected function enrol(string $number, string $first, ?Section $section = null): Student
    {
        $section ??= $this->section;

        $student = Student::create([
            'school_id' => $this->school->id, 'student_number' => $number,
            'first_name' => $first, 'last_name' => 'Doe', 'status' => 'active',
        ]);

        Enrollment::create([
            'school_id' => $this->school->id, 'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $section->school_class_id,
            'section_id' => $section->id, 'status' => 'active',
        ]);

        return $student;
    }

    protected function paper(string $name = 'question-paper.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 120, 'application/pdf');
    }

    protected function assessmentWithPaper(): Assessment
    {
        $this->actingAs($this->teacherUser())->post(route('assessments.store'), [
            'section_id' => $this->section->id,
            'subject_id' => $this->maths->id,
            'title' => 'Essay on Liberian history',
            'type' => 'assignment',
            'max_score' => 20,
            'term_id' => $this->term->id,
            'instructions' => "Answer all three questions.\n\nShow your working.",
            'question_paper' => $this->paper(),
        ]);

        return Assessment::firstOrFail();
    }

    /* ------------------------------------------------------- setting it */

    public function test_a_teacher_sets_a_question_and_attaches_a_paper(): void
    {
        $assessment = $this->assessmentWithPaper();

        $this->assertStringContainsString('Answer all three questions.', $assessment->instructions);
        $this->assertSame('question-paper.pdf', $assessment->attachment_name);

        // Private disk: never a public URL.
        Storage::disk('local')->assertExists($assessment->attachment_path);
        $this->assertStringStartsWith('assessments/', $assessment->attachment_path);
    }

    public function test_a_question_is_optional(): void
    {
        $this->actingAs($this->teacherUser())
            ->post(route('assessments.store'), [
                'section_id' => $this->section->id,
                'subject_id' => $this->maths->id,
                'title' => 'Quick test',
                'type' => 'test',
                'max_score' => 10,
            ])
            ->assertRedirect();

        $this->assertNull(Assessment::firstOrFail()->instructions);
    }

    public function test_only_a_pdf_or_word_document_is_accepted(): void
    {
        $this->actingAs($this->teacherUser())
            ->post(route('assessments.store'), [
                'section_id' => $this->section->id,
                'subject_id' => $this->maths->id,
                'title' => 'Test',
                'type' => 'test',
                'max_score' => 10,
                'question_paper' => UploadedFile::fake()->create('script.exe', 10),
            ])
            ->assertSessionHasErrors('question_paper');

        $this->assertSame(0, Assessment::count());
    }

    public function test_replacing_the_paper_removes_the_old_file(): void
    {
        $assessment = $this->assessmentWithPaper();
        $original = $assessment->attachment_path;

        $this->actingAs($this->teacherUser())->put(route('assessments.update', $assessment), [
            'title' => $assessment->title,
            'type' => $assessment->type,
            'max_score' => $assessment->max_score,
            'question_paper' => $this->paper('revised-paper.pdf'),
        ]);

        $assessment->refresh();

        $this->assertSame('revised-paper.pdf', $assessment->attachment_name);

        // Otherwise every revision quietly fills the disk.
        Storage::disk('local')->assertMissing($original);
        Storage::disk('local')->assertExists($assessment->attachment_path);
    }

    public function test_editing_without_a_new_file_keeps_the_one_attached(): void
    {
        $assessment = $this->assessmentWithPaper();
        $path = $assessment->attachment_path;

        $this->actingAs($this->teacherUser())->put(route('assessments.update', $assessment), [
            'title' => 'Renamed essay',
            'type' => $assessment->type,
            'max_score' => $assessment->max_score,
        ]);

        $assessment->refresh();

        $this->assertSame('Renamed essay', $assessment->title);
        $this->assertSame($path, $assessment->attachment_path);
    }

    /* --------------------------------------------------------- seeing it */

    public function test_a_student_sees_the_question_and_can_open_the_paper(): void
    {
        $assessment = $this->assessmentWithPaper();

        $student = $this->enrol('S-1', 'Ada');
        $studentUser = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $student->update(['user_id' => $studentUser->id]);

        $this->actingAs($studentUser)
            ->get(route('student.assignments'))
            ->assertOk()
            ->assertSee('Answer all three questions.')
            ->assertSee('question-paper.pdf');

        $this->actingAs($studentUser)
            ->get(route('assessments.question', $assessment))
            ->assertOk()
            ->assertDownload('question-paper.pdf');
    }

    public function test_a_parent_sees_the_question_and_can_open_the_paper(): void
    {
        $assessment = $this->assessmentWithPaper();

        $student = $this->enrol('S-1', 'Ada');

        $guardian = Guardian::create([
            'school_id' => $this->school->id, 'first_name' => 'John', 'last_name' => 'Doe',
        ]);

        $parent = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $guardian->update(['user_id' => $parent->id]);

        $student->guardians()->attach($guardian->id, [
            'relationship' => 'Father', 'is_primary' => true, 'can_view_academics' => true,
        ]);

        /*
         | The half that did not exist. A question that lives on a sheet of
         | paper in a schoolbag is one a parent cannot help with.
         */
        $this->actingAs($parent)
            ->get(route('parent.assignments'))
            ->assertOk()
            ->assertSee('Essay on Liberian history')
            ->assertSee('Answer all three questions.');

        $this->actingAs($parent)
            ->get(route('assessments.question', $assessment))
            ->assertOk()
            ->assertDownload('question-paper.pdf');
    }

    /* ------------------------------------------------- who may not open it */

    public function test_a_student_in_another_class_cannot_open_the_paper(): void
    {
        $assessment = $this->assessmentWithPaper();

        $otherSection = Section::create([
            'school_id' => $this->school->id,
            'school_class_id' => $this->section->school_class_id,
            'name' => 'B',
        ]);

        $outsider = $this->enrol('S-9', 'Elsewhere', $otherSection);
        $outsiderUser = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $outsider->update(['user_id' => $outsiderUser->id]);

        // A paper set for 9A is not for 9B to read before they sit it.
        $this->actingAs($outsiderUser)
            ->get(route('assessments.question', $assessment))
            ->assertForbidden();
    }

    public function test_a_parent_not_cleared_for_academics_cannot_open_the_paper(): void
    {
        $assessment = $this->assessmentWithPaper();

        $student = $this->enrol('S-1', 'Ada');

        $guardian = Guardian::create([
            'school_id' => $this->school->id, 'first_name' => 'Not', 'last_name' => 'Cleared',
        ]);

        $parent = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $guardian->update(['user_id' => $parent->id]);

        $student->guardians()->attach($guardian->id, [
            'relationship' => 'Uncle', 'is_primary' => false, 'can_view_academics' => false,
        ]);

        $this->actingAs($parent)
            ->get(route('assessments.question', $assessment))
            ->assertForbidden();
    }

    public function test_a_paper_from_another_school_is_not_found(): void
    {
        $assessment = $this->assessmentWithPaper();

        $other = $this->createSchool(['name' => 'Another School']);
        $stranger = User::factory()->create(['school_id' => $other->id, 'status' => 'active']);

        $this->actingAs($stranger)
            ->get(route('assessments.question', $assessment))
            ->assertNotFound();
    }

    public function test_an_assessment_with_no_paper_answers_not_found(): void
    {
        $this->actingAs($this->teacherUser())->post(route('assessments.store'), [
            'section_id' => $this->section->id,
            'subject_id' => $this->maths->id,
            'title' => 'No paper',
            'type' => 'test',
            'max_score' => 10,
        ]);

        $this->actingAs($this->teacherUser())
            ->get(route('assessments.question', Assessment::firstOrFail()))
            ->assertNotFound();
    }

    public function test_the_question_panel_is_absent_when_nothing_was_set(): void
    {
        $this->actingAs($this->teacherUser())->post(route('assessments.store'), [
            'section_id' => $this->section->id,
            'subject_id' => $this->maths->id,
            'title' => 'Bare test',
            'type' => 'assignment',
            'max_score' => 10,
        ]);

        $student = $this->enrol('S-1', 'Ada');
        $studentUser = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $student->update(['user_id' => $studentUser->id]);

        StudentPermission::create([
            'school_id' => $this->school->id, 'student_id' => $student->id,
            'ability' => 'view_assignments', 'allowed' => true,
        ]);

        // An empty panel would imply a question was set and is missing.
        $this->actingAs($studentUser)
            ->get(route('student.assignments'))
            ->assertOk()
            ->assertSee('Bare test')
            ->assertDontSee('The question');
    }
}
