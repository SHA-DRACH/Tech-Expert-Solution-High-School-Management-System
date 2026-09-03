<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Enrollment;
use App\Models\ReportCard;
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
use Tests\TestCase;

/**
 * The student portal (spec section 23).
 *
 * Two things are being defended. The modules exist and are reachable; and the
 * sentence that section ends on — "students must only see their own records" —
 * holds on every one of them.
 */
class StudentPortalModulesTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected Term $term;

    protected Section $mine;

    protected Section $theirs;

    protected Student $student;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->mine = Section::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => 'A']);
        $this->theirs = Section::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => 'B']);

        $maths = Subject::create(['school_id' => $this->school->id, 'name' => 'Mathematics', 'code' => 'MTH']);
        $class->subjects()->attach($maths->id, ['school_id' => $this->school->id]);

        $this->student = $this->enrol($this->mine, 'S-1', 'Ada');

        $this->user = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $this->student->update(['user_id' => $this->user->id]);
    }

    protected function enrol(Section $section, string $number, string $first): Student
    {
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

    protected function allow(string $ability): void
    {
        $this->setAbility($ability, true);
    }

    protected function deny(string $ability): void
    {
        $this->setAbility($ability, false);
    }

    protected function setAbility(string $ability, bool $allowed): void
    {
        StudentPermission::updateOrCreate(
            ['school_id' => $this->school->id, 'student_id' => $this->student->id, 'ability' => $ability],
            ['allowed' => $allowed],
        );
    }

    /* --------------------------------------------------------- the shell */

    public function test_a_student_lands_on_their_dashboard_with_a_sidebar(): void
    {
        $this->actingAs($this->user)->get(route('portal'))->assertRedirect(route('student.dashboard'));

        $html = $this->actingAs($this->user)->get(route('student.dashboard'))->assertOk()->getContent();

        /*
         | Students used to get a separate top-bar layout with no side
         | navigation, so they saw a different application from the one their
         | school used. There is now one shell for everybody.
         */
        $this->assertStringContainsString('My learning', $html);
        $this->assertStringContainsString('My account', $html);
        $this->assertStringContainsString(route('profile.edit'), $html);
        $this->assertStringContainsString(route('notifications.index'), $html);
    }

    public function test_a_student_holding_no_permissions_still_gets_a_menu(): void
    {
        /*
         | Students hold no permission slugs at all. The sidebar filters on
         | permissions, so without a "no permission needed" entry their whole
         | menu — including their own profile — filtered itself away to nothing.
         */
        $html = $this->actingAs($this->user)->get(route('student.dashboard'))->assertOk()->getContent();

        $this->assertSame([], $this->user->roles->flatMap->permissions->pluck('slug')->all());
        $this->assertStringContainsString(route('student.dashboard'), $html);
        $this->assertStringContainsString(route('profile.edit'), $html);
    }

    /* ------------------------------------------------------- the modules */

    public function test_every_module_in_the_spec_opens_once_allowed(): void
    {
        foreach (['view_subjects', 'view_grades', 'view_attendance', 'view_timetable', 'view_assignments', 'view_report_cards'] as $ability) {
            $this->allow($ability);
        }

        foreach ([
            'student.dashboard', 'student.class', 'student.subjects', 'student.grades',
            'student.exams', 'student.assignments', 'student.reportcards',
            'student.attendance', 'student.timetable', 'student.announcements',
            'profile.edit', 'notifications.index',
        ] as $route) {
            $this->actingAs($this->user)->get(route($route))->assertOk();
        }
    }

    public function test_a_module_the_school_switched_off_is_refused(): void
    {
        // `view_subjects` is on by default, so this starts open.
        $this->actingAs($this->user)->get(route('student.subjects'))->assertOk();

        // The administrator controls what a student may open (section 23).
        $this->deny('view_subjects');

        $this->actingAs($this->user)->get(route('student.subjects'))->assertForbidden();
    }

    public function test_a_switched_off_module_is_not_offered_in_the_menu(): void
    {
        $html = $this->actingAs($this->user)->get(route('student.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString(route('student.subjects'), $html);

        $this->deny('view_subjects');

        $html = $this->actingAs($this->user)->get(route('student.dashboard'))->assertOk()->getContent();

        // Offering a link that answers 403 is worse than not offering it.
        $this->assertStringNotContainsString(route('student.subjects'), $html);
    }

    /* ------------------------- students must only see their own records */

    public function test_the_class_page_shows_a_headcount_not_a_roster(): void
    {
        $this->allow('view_subjects');

        $this->enrol($this->mine, 'S-2', 'Classmate');

        // A student has no business reading a list of their classmates.
        $this->actingAs($this->user)
            ->get(route('student.class'))
            ->assertOk()
            ->assertSee('Grade 9A')
            ->assertDontSee('Classmate Doe');
    }

    public function test_only_this_students_own_report_cards_are_listed(): void
    {
        $this->allow('view_report_cards');

        $other = $this->enrol($this->theirs, 'S-9', 'Other');

        foreach ([[$this->student, 88], [$other, 44]] as [$who, $average]) {
            ReportCard::create([
                'school_id' => $this->school->id, 'student_id' => $who->id,
                'academic_year_id' => $this->year->id, 'term_id' => $this->term->id,
                'section_id' => $who->currentEnrollment?->section_id,
                'average' => $average, 'status' => 'published', 'published_at' => now(),
            ]);
        }

        $mine = ReportCard::where('student_id', $this->student->id)->firstOrFail();
        $hers = ReportCard::where('student_id', $other->id)->firstOrFail();

        /*
         | Asserted on the card's own link rather than on the average: a bare
         | "44" could appear anywhere in the markup — a date, an id, a class
         | name — and would pass or fail for reasons unrelated to the rule.
         */
        $this->actingAs($this->user)
            ->get(route('student.reportcards'))
            ->assertOk()
            ->assertSee(route('reportcards.show', $mine))
            ->assertDontSee(route('reportcards.show', $hers));
    }

    public function test_only_papers_for_this_students_class_are_listed(): void
    {
        $this->allow('view_grades');

        $maths = Subject::where('code', 'MTH')->firstOrFail();

        foreach ([[$this->mine, 'My class paper'], [$this->theirs, 'Another class paper']] as [$section, $title]) {
            Assessment::create([
                'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
                'term_id' => $this->term->id, 'section_id' => $section->id,
                'subject_id' => $maths->id, 'title' => $title, 'type' => 'exam', 'max_score' => 100,
            ]);
        }

        $this->actingAs($this->user)
            ->get(route('student.exams'))
            ->assertOk()
            ->assertSee('My class paper')
            ->assertDontSee('Another class paper');
    }

    public function test_an_announcement_aimed_at_another_class_is_not_shown(): void
    {
        foreach ([[$this->mine->id, 'For my class'], [$this->theirs->id, 'For the other class'], [null, 'For everyone']] as [$sectionId, $title]) {
            Announcement::create([
                'school_id' => $this->school->id,
                'section_id' => $sectionId,
                'title' => $title,
                'body' => 'A notice.',
                'category' => Announcement::CATEGORIES[0],
                'audience' => ['students'],
                'published_at' => now()->subHour(),
                'status' => 'published',
            ]);
        }

        $this->actingAs($this->user)
            ->get(route('student.announcements'))
            ->assertOk()
            ->assertSee('For my class')
            ->assertSee('For everyone')
            ->assertDontSee('For the other class');
    }

    public function test_grades_show_only_this_students_own_approved_marks(): void
    {
        $this->allow('view_grades');

        $maths = Subject::where('code', 'MTH')->firstOrFail();
        $other = $this->enrol($this->mine, 'S-2', 'Classmate');

        $assessment = Assessment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'term_id' => $this->term->id, 'section_id' => $this->mine->id,
            'subject_id' => $maths->id, 'title' => 'Test', 'type' => 'test',
            'max_score' => 100, 'status' => 'approved',
        ]);

        AssessmentScore::create([
            'school_id' => $this->school->id, 'assessment_id' => $assessment->id,
            'student_id' => $this->student->id, 'score' => 77,
        ]);

        AssessmentScore::create([
            'school_id' => $this->school->id, 'assessment_id' => $assessment->id,
            'student_id' => $other->id, 'score' => 31,
        ]);

        $this->actingAs($this->user)
            ->get(route('student.grades'))
            ->assertOk()
            ->assertSee('77')
            ->assertDontSee('31');
    }

    public function test_the_portal_is_closed_to_an_account_with_no_student_record(): void
    {
        $stranger = $this->userFor($this->school, ['dashboard.view']);

        foreach (['student.subjects', 'student.class', 'student.exams', 'student.reportcards', 'student.announcements'] as $route) {
            $this->actingAs($stranger)->get(route($route))->assertForbidden();
        }
    }

    public function test_the_old_top_bar_layout_is_gone(): void
    {
        /*
         | The user asked for one shell and only one. A second layout lying
         | around is how a page quietly gets built on the wrong one later.
         */
        $this->assertFileDoesNotExist(resource_path('views/components/layouts/portal.blade.php'));

        foreach (glob(resource_path('views/portals/*/*.blade.php')) as $view) {
            $this->assertStringNotContainsString(
                'x-layouts.portal',
                file_get_contents($view),
                basename($view).' still uses the removed portal layout.',
            );
        }
    }
}
