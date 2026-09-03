<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Services\PublicVisibility;
use App\Support\Money;
use App\Support\SchoolContext;
use Database\Seeders\WebsiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec sections 7 to 11: the public website.
 *
 * The rule these tests exist to protect is section 10 and 11's: the school
 * decides what is published, and fees are not published unless it says so.
 */
class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool([
            'name' => 'Grace Foundation Institution',
            'short_name' => 'Grace Foundation',
            'motto' => 'Inspiring excellence, character, and service.',
        ]);

        app(SchoolContext::class)->setSchool($this->school);

        $this->seed(WebsiteContentSeeder::class);

        $year = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026 / 2027',
            'starts_on' => now()->subMonth(),
            'ends_on' => now()->addMonths(9),
            'is_current' => true,
        ]);

        Term::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $year->id,
            'name' => 'First Term',
            'sequence' => 1,
            'starts_on' => now()->subMonth(),
            'ends_on' => now()->addMonths(2),
            'is_current' => true,
        ]);

        $department = Department::create(['school_id' => $this->school->id, 'name' => 'Sciences']);

        Subject::create([
            'school_id' => $this->school->id,
            'department_id' => $department->id,
            'name' => 'Biology',
            'code' => 'BIO101',
        ]);

        foreach ([7, 11] as $level) {
            SchoolClass::create([
                'school_id' => $this->school->id,
                'name' => "Grade {$level}",
                'level' => $level,
                'stage' => $level >= 10 ? 'Senior High' : 'Junior High',
            ]);
        }
    }

    protected function publishedTeacher(array $overrides = []): Teacher
    {
        return Teacher::create(array_merge([
            'school_id' => $this->school->id,
            'staff_number' => 'T-'.fake()->unique()->numberBetween(100, 999),
            'first_name' => 'Grace',
            'last_name' => 'Kollie',
            'status' => 'active',
            'is_public' => true,
            'biography' => 'Teaches mathematics across the junior high.',
        ], $overrides));
    }

    protected function setVisibility(array $switches): void
    {
        app(PublicVisibility::class)->update($this->school->id, $switches);

        // The service memoises per request; a fresh one reads the new values.
        $this->app->forgetInstance(PublicVisibility::class);
    }

    /** Every switch on, so a test can turn one off in isolation. */
    protected function allSwitchesOn(): array
    {
        return collect(PublicVisibility::SWITCHES)->map(fn () => true)->all();
    }

    /* ------------------------------------------------------------ the site */

    public function test_the_homepage_shows_the_school_identity_and_both_calls_to_action(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Grace Foundation Institution')
            ->assertSee('Inspiring excellence, character, and service.')
            ->assertSee('Apply for admission')
            ->assertSee('Parent login');
    }

    public function test_the_navigation_carries_every_public_page(): void
    {
        $this->setVisibility($this->allSwitchesOn());

        $response = $this->get(route('home'))->assertOk();

        foreach (['About', 'Academics', 'Admissions', 'Teachers', 'News', 'Events', 'Gallery', 'Contact'] as $label) {
            $response->assertSee($label);
        }
    }

    public function test_the_about_page_covers_what_the_spec_asks_for(): void
    {
        $this->get(route('public.about'))
            ->assertOk()
            ->assertSee('Our story')
            ->assertSee('Our mission')
            ->assertSee('Our vision')
            ->assertSee('Our values')
            ->assertSee('Principal', false)
            ->assertSee('Facilities')
            ->assertSee('School policies');
    }

    public function test_the_academics_page_lists_the_real_classes_and_subjects(): void
    {
        $this->setVisibility($this->allSwitchesOn());

        $this->get(route('public.academics'))
            ->assertOk()
            ->assertSee('Junior High')
            ->assertSee('Senior High')
            ->assertSee('Grade 7')
            ->assertSee('Grade 11')
            ->assertSee('Sciences')
            ->assertSee('Biology')
            ->assertSee('First Term');
    }

    public function test_the_admissions_page_explains_the_process_and_documents(): void
    {
        $this->setVisibility($this->allSwitchesOn());

        $this->get(route('public.admissions'))
            ->assertOk()
            ->assertSee('How admission works')
            ->assertSee('Required documents')
            ->assertSee('Birth certificate')
            ->assertSee('Available classes')
            ->assertSee('Apply online');
    }

    public function test_the_teachers_page_shows_published_staff_only(): void
    {
        $this->setVisibility($this->allSwitchesOn());

        $this->publishedTeacher(['first_name' => 'Published', 'last_name' => 'Teacher']);
        $this->publishedTeacher(['first_name' => 'Private', 'last_name' => 'Teacher', 'is_public' => false]);

        $this->get(route('public.teachers'))
            ->assertOk()
            ->assertSee('Published Teacher')
            ->assertDontSee('Private Teacher');
    }

    public function test_only_qualifications_marked_public_appear(): void
    {
        $this->setVisibility($this->allSwitchesOn());

        $teacher = $this->publishedTeacher();

        $teacher->qualifications()->create([
            'school_id' => $this->school->id,
            'type' => 'qualification',
            'title' => 'BSc Mathematics',
            'is_public' => true,
        ]);

        $teacher->qualifications()->create([
            'school_id' => $this->school->id,
            'type' => 'certification',
            'title' => 'Confidential internal award',
            'is_public' => false,
        ]);

        $this->get(route('public.teachers'))
            ->assertOk()
            ->assertSee('BSc Mathematics')
            ->assertDontSee('Confidential internal award');
    }

    public function test_the_statistics_carry_the_real_figures_without_javascript(): void
    {
        $this->setVisibility($this->allSwitchesOn());

        $this->publishedTeacher();

        /*
         | The counter animation blanks these to zero itself when it starts. The
         | server must never render the nought: a visitor with JavaScript off
         | would be told the school has no students, no staff and no classes.
         | setUp() creates two classes and one subject, so the figures are known.
         */
        foreach ([['2', 'Classes offered'], ['1', 'Subjects taught'], ['1', 'Teaching staff']] as [$figure, $label]) {
            $this->get(route('public.about'))
                ->assertOk()
                ->assertSee('data-counter="'.$figure.'"', false)
                ->assertSee($label);
        }

        $this->get(route('public.about'))
            ->assertOk()
            ->assertDontSee('data-counter="2" data-suffix="">0</span>', false);
    }

    /* --------------------------------------------------- what is published */

    public function test_fees_are_not_published_by_default(): void
    {
        $this->feeStructure();

        // No visibility row at all: the catalogue default for fees is off.
        $this->get(route('public.admissions'))
            ->assertOk()
            ->assertDontSee('What it costs')
            ->assertDontSee('L$ 16,500.00');
    }

    public function test_fees_appear_only_once_the_school_publishes_them(): void
    {
        $this->feeStructure();

        $this->setVisibility($this->allSwitchesOn());

        $this->get(route('public.admissions'))
            ->assertOk()
            ->assertSee('What it costs')
            ->assertSee('L$ 16,500.00');
    }

    public function test_switching_a_section_off_removes_the_page_and_its_link(): void
    {
        $this->setVisibility($this->allSwitchesOn());

        $this->publishedTeacher();

        $this->get(route('public.teachers'))->assertOk();

        $this->setVisibility(['teachers' => false] + $this->allSwitchesOn());

        // The page is gone, and so is the link that pointed at it.
        $this->get(route('public.teachers'))->assertNotFound();
        $this->get(route('home'))->assertOk()->assertDontSee('Meet the staff');
    }

    public function test_hiding_subjects_keeps_them_off_the_academics_page(): void
    {
        $this->setVisibility(['subjects' => false, 'departments' => false] + $this->allSwitchesOn());

        $this->get(route('public.academics'))
            ->assertOk()
            ->assertDontSee('Every subject we offer')
            ->assertDontSee('How teaching is organised');
    }

    public function test_hiding_the_calendar_keeps_term_dates_private(): void
    {
        $this->setVisibility($this->allSwitchesOn());

        // Asserted on the section's own label rather than the words "term
        // dates", which also appear in the page copy the school has written.
        $this->get(route('public.academics'))
            ->assertOk()
            ->assertSee('Academic calendar')
            ->assertSee('First Term');

        $this->setVisibility(['calendar' => false] + $this->allSwitchesOn());

        $this->get(route('public.academics'))
            ->assertOk()
            ->assertDontSee('Academic calendar');
    }

    public function test_an_administrator_can_change_what_is_published(): void
    {
        $administrator = $this->administratorFor($this->school);

        $this->actingAs($administrator)
            ->put(route('website.visibility'), [
                'visibility' => ['fees' => '1', 'teachers' => '1'],
            ])
            ->assertRedirect();

        $this->app->forgetInstance(PublicVisibility::class);

        $visibility = app(PublicVisibility::class);

        $this->assertTrue($visibility->shows('fees'));
        $this->assertTrue($visibility->shows('teachers'));

        // Anything left unticked is switched off.
        $this->assertFalse($visibility->shows('gallery'));

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Website',
            'action' => 'updated',
        ]);
    }

    public function test_changing_visibility_needs_the_website_permission(): void
    {
        $user = $this->userFor($this->school, ['dashboard.view']);

        $this->actingAs($user)
            ->put(route('website.visibility'), ['visibility' => ['fees' => '1']])
            ->assertForbidden();
    }

    /* ------------------------------------------------------------------ */

    protected function feeStructure(): FeeStructure
    {
        $structure = FeeStructure::create([
            'school_id' => $this->school->id,
            'academic_year_id' => AcademicYear::first()->id,
            'name' => 'Grade 7 — First Term',
            'is_active' => true,
        ]);

        foreach ([['Tuition', 15000], ['ICT', 1500]] as [$category, $amount]) {
            FeeItem::create([
                'school_id' => $this->school->id,
                'fee_structure_id' => $structure->id,
                'category' => $category,
                'amount_minor' => Money::toMinor($amount),
            ]);
        }

        return $structure;
    }
}
