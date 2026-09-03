<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The teacher portal (spec section 27).
 *
 * Half of these check the modules exist. The other half check the sentence the
 * spec ends on — "teachers must only access authorized classes and students" —
 * which is the part that matters, because every one of these screens takes a
 * section or subject and could widen what a teacher sees if it trusted it.
 */
class TeacherPortalModulesTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected Term $term;

    protected Section $mine;

    protected Section $theirs;

    protected Subject $maths;

    protected Subject $english;

    protected Teacher $teacher;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();
        app(SchoolContext::class)->setSchool($this->school);

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026 / 2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'is_current' => true,
        ]);

        $this->term = Term::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'name' => 'First Term',
            'sequence' => 1,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-12-15',
            'is_current' => true,
        ]);

        $class = SchoolClass::create([
            'school_id' => $this->school->id, 'name' => 'Grade 9', 'level' => 9,
        ]);

        $this->mine = Section::create([
            'school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => 'A',
        ]);

        $this->theirs = Section::create([
            'school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => 'B',
        ]);

        $this->maths = Subject::create(['school_id' => $this->school->id, 'name' => 'Mathematics', 'code' => 'MTH']);
        $this->english = Subject::create(['school_id' => $this->school->id, 'name' => 'English', 'code' => 'ENG']);

        $class->subjects()->attach([$this->maths->id, $this->english->id], ['school_id' => $this->school->id]);

        $this->user = $this->userFor($this->school, [
            'dashboard.view', 'grades.enter', 'exams.view', 'attendance.view', 'attendance.record', 'students.view',
        ]);

        $this->teacher = Teacher::create([
            'school_id' => $this->school->id,
            'staff_number' => 'T-001',
            'first_name' => 'Grace',
            'last_name' => 'Kollie',
            'status' => 'active',
            'user_id' => $this->user->id,
        ]);

        // Assigned to Mathematics in section A only.
        TeachingAssignment::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'teacher_id' => $this->teacher->id,
            'section_id' => $this->mine->id,
            'subject_id' => $this->maths->id,
        ]);
    }

    protected function student(Section $section, string $number, string $first): Student
    {
        $student = Student::create([
            'school_id' => $this->school->id,
            'student_number' => $number,
            'first_name' => $first,
            'last_name' => 'Doe',
            'status' => 'active',
        ]);

        Enrollment::create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $section->school_class_id,
            'section_id' => $section->id,
            'status' => 'active',
        ]);

        return $student;
    }

    /* ------------------------------------------------------- the modules */

    public function test_every_module_in_the_spec_opens(): void
    {
        foreach ([
            'teaching.dashboard', 'teaching.classes', 'teaching.subjects', 'teaching.students',
            'teaching.assignments', 'teaching.timetable', 'teaching.announcements',
            'profile.edit', 'notifications.index',
        ] as $route) {
            $this->actingAs($this->user)->get(route($route))->assertOk();
        }
    }

    public function test_the_sidebar_offers_every_module(): void
    {
        $html = $this->actingAs($this->user)->get(route('teaching.dashboard'))->assertOk()->getContent();

        foreach ([
            'My classes', 'My subjects', 'My students', 'Assignments', 'My timetable',
            'Announcements', 'Attendance', 'Examinations', 'Mark sheet', 'Notifications', 'My profile',
        ] as $label) {
            $this->assertStringContainsString($label, $html, "The sidebar should offer {$label}.");
        }
    }

    /* --------------------------------------------------------- dashboard */

    public function test_the_dashboard_shows_an_attendance_rate_not_a_row_count(): void
    {
        $student = $this->student($this->mine, 'S-1', 'Ada');

        foreach ([['present', 9], ['absent', 1]] as [$status, $times]) {
            for ($i = 0; $i < $times; $i++) {
                AttendanceRecord::create([
                    'school_id' => $this->school->id,
                    'student_id' => $student->id,
                    'section_id' => $this->mine->id,
                    'academic_year_id' => $this->year->id,
                    'term_id' => $this->term->id,
                    'recorded_on' => now()->subDays($i + ($status === 'absent' ? 20 : 0))->toDateString(),
                    'status' => $status,
                ]);
            }
        }

        // 9 of 10 present.
        $this->actingAs($this->user)
            ->get(route('teaching.dashboard'))
            ->assertOk()
            ->assertSee('90%');
    }

    public function test_attendance_reads_as_unknown_rather_than_zero_when_no_register_exists(): void
    {
        $this->student($this->mine, 'S-1', 'Ada');

        /*
         | A class whose register has never been taken is not a class nobody
         | attends. Showing 0% would read as a crisis rather than missing data.
         */
        $this->actingAs($this->user)
            ->get(route('teaching.dashboard'))
            ->assertOk()
            ->assertSee('No register taken yet');
    }

    /* ------------------------------------------------------- the boundary */

    public function test_only_assigned_subjects_are_listed(): void
    {
        $html = $this->actingAs($this->user)->get(route('teaching.subjects'))->assertOk()->getContent();

        $this->assertStringContainsString('Mathematics', $html);

        // The class does English too, but this teacher does not teach it.
        $this->assertStringNotContainsString('English', $html);
    }

    public function test_only_students_in_assigned_classes_are_listed(): void
    {
        $this->student($this->mine, 'S-1', 'Mine');
        $this->student($this->theirs, 'S-2', 'Theirs');

        $this->actingAs($this->user)
            ->get(route('teaching.students'))
            ->assertOk()
            ->assertSee('Mine Doe')
            ->assertDontSee('Theirs Doe');
    }

    public function test_filtering_by_an_unassigned_class_is_refused(): void
    {
        $this->student($this->theirs, 'S-2', 'Theirs');

        // A section id in the URL must not widen what a teacher reaches.
        $this->actingAs($this->user)
            ->get(route('teaching.students', ['section' => $this->theirs->id]))
            ->assertForbidden();
    }

    public function test_only_the_teachers_own_assignments_are_listed(): void
    {
        $other = Teacher::create([
            'school_id' => $this->school->id,
            'staff_number' => 'T-002',
            'first_name' => 'Someone',
            'last_name' => 'Else',
            'status' => 'active',
        ]);

        Assessment::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term->id,
            'section_id' => $this->mine->id,
            'subject_id' => $this->maths->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'My essay task',
            'type' => 'assignment',
            'max_score' => 20,
        ]);

        Assessment::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term->id,
            'section_id' => $this->mine->id,
            'subject_id' => $this->maths->id,
            'teacher_id' => $other->id,
            'title' => 'Someone elses task',
            'type' => 'assignment',
            'max_score' => 20,
        ]);

        $this->actingAs($this->user)
            ->get(route('teaching.assignments'))
            ->assertOk()
            ->assertSee('My essay task')
            ->assertDontSee('Someone elses task');
    }

    public function test_the_assignment_list_counts_who_has_handed_in(): void
    {
        $ada = $this->student($this->mine, 'S-1', 'Ada');
        $this->student($this->mine, 'S-2', 'Ben');

        $assignment = Assessment::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term->id,
            'section_id' => $this->mine->id,
            'subject_id' => $this->maths->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Essay',
            'type' => 'assignment',
            'max_score' => 20,
            'ends_at' => now()->subDay(),
        ]);

        AssignmentSubmission::create([
            'school_id' => $this->school->id,
            'assessment_id' => $assignment->id,
            'student_id' => $ada->id,
            'submitted_at' => now()->subDays(2),
        ]);

        // "Who still owes me work?" is the question this screen exists for.
        $this->actingAs($this->user)
            ->get(route('teaching.assignments'))
            ->assertOk()
            ->assertSee('1 of 2')
            ->assertSee('1 still owing');
    }

    public function test_a_late_submission_is_counted_against_the_closing_time(): void
    {
        $ada = $this->student($this->mine, 'S-1', 'Ada');

        $assignment = Assessment::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term->id,
            'section_id' => $this->mine->id,
            'subject_id' => $this->maths->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Essay',
            'type' => 'assignment',
            'max_score' => 20,
            'ends_at' => '2026-10-12 10:30',
        ]);

        AssignmentSubmission::create([
            'school_id' => $this->school->id,
            'assessment_id' => $assignment->id,
            'student_id' => $ada->id,
            'submitted_at' => '2026-10-12 10:45',
        ]);

        $this->actingAs($this->user)
            ->get(route('teaching.assignments'))
            ->assertOk()
            ->assertSee('1 late');
    }

    public function test_announcements_for_teachers_are_shown_and_others_are_not(): void
    {
        Announcement::create([
            'school_id' => $this->school->id,
            'title' => 'Staff meeting Friday',
            'body' => 'All teaching staff to attend.',
            'category' => Announcement::CATEGORIES[0],
            'audience' => ['teachers'],
            'published_at' => now()->subHour(),
            'status' => 'published',
        ]);

        Announcement::create([
            'school_id' => $this->school->id,
            'title' => 'Fees due this month',
            'body' => 'A notice for families only.',
            'category' => Announcement::CATEGORIES[0],
            'audience' => ['parents'],
            'published_at' => now()->subHour(),
            'status' => 'published',
        ]);

        $this->actingAs($this->user)
            ->get(route('teaching.announcements'))
            ->assertOk()
            ->assertSee('Staff meeting Friday')
            ->assertDontSee('Fees due this month');
    }

    public function test_the_portal_is_closed_to_an_account_with_no_staff_record(): void
    {
        $stranger = $this->userFor($this->school, ['dashboard.view']);

        foreach (['teaching.subjects', 'teaching.assignments', 'teaching.announcements'] as $route) {
            $this->actingAs($stranger)->get(route($route))->assertForbidden();
        }
    }
}
