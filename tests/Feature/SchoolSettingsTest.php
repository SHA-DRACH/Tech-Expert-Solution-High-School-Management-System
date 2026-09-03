<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\Term;
use App\Services\SchoolSettings;
use App\Support\Money;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec section 57: the settings a school decides for itself.
 *
 * The rule worth protecting here is that a setting is only worth having if the
 * application actually reads it, so most of these tests change a setting and
 * then check the behaviour it governs, not the row it wrote.
 */
class SchoolSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool(['name' => 'Grace Foundation Institution']);

        app(SchoolContext::class)->setSchool($this->school);
    }

    protected function settings(): SchoolSettings
    {
        return app(SchoolSettings::class);
    }

    /* ------------------------------------------------------------ defaults */

    public function test_every_setting_falls_back_to_its_catalogue_default(): void
    {
        $values = $this->settings()->all();

        foreach (SchoolSettings::catalogue() as $key => $definition) {
            $this->assertSame($definition['default'], $values[$key], "Default wrong for [{$key}].");
        }
    }

    public function test_an_administrator_can_change_a_group_of_settings(): void
    {
        $administrator = $this->administratorFor($this->school);

        $this->actingAs($administrator)
            ->put(route('settings.group.update', 'finance'), [
                'settings' => [
                    'finance_currency' => 'US$',
                    'finance_invoice_due_days' => '14',
                    'finance_reminder_days_before' => '3',
                    'finance_reminder_cooldown_days' => '5',
                    'finance_receipt_footer' => 'Thank you.',
                ],
            ])
            ->assertRedirect(route('settings.group.edit', 'finance'));

        $values = $this->settings()->all();

        $this->assertSame('US$', $values['finance_currency']);
        $this->assertSame(14, $values['finance_invoice_due_days']);
        $this->assertSame('Thank you.', $values['finance_receipt_footer']);

        $this->assertDatabaseHas('audit_logs', ['module' => 'Settings', 'action' => 'updated']);
    }

    public function test_saving_one_group_leaves_the_other_groups_alone(): void
    {
        // An unticked checkbox submits nothing at all, so a form that wrote
        // every key it did not mention would switch off the other tabs.
        $this->settings()->updateGroup($this->school->id, 'academics', [
            'attendance_notify_guardians' => '1',
            'reportcard_show_position' => '1',
            'grading_pass_mark' => 60,
        ]);

        $this->actingAs($this->administratorFor($this->school))
            ->put(route('settings.group.update', 'finance'), [
                'settings' => ['finance_currency' => 'US$'],
            ])
            ->assertRedirect();

        $values = $this->settings()->all();

        $this->assertTrue($values['attendance_notify_guardians']);
        $this->assertSame(60, $values['grading_pass_mark']);
        $this->assertSame('US$', $values['finance_currency']);
    }

    public function test_changing_settings_needs_the_settings_permission(): void
    {
        $user = $this->userFor($this->school, ['dashboard.view']);

        $this->actingAs($user)
            ->put(route('settings.group.update', 'finance'), ['settings' => ['finance_currency' => 'US$']])
            ->assertForbidden();

        $this->actingAs($user)->get(route('settings.index'))->assertForbidden();
    }

    public function test_an_unknown_settings_group_is_not_found(): void
    {
        $this->actingAs($this->administratorFor($this->school))
            ->get(route('settings.group.edit', 'nonsense'))
            ->assertNotFound();
    }

    /* --------------------------------------------- the settings take effect */

    public function test_the_currency_symbol_follows_the_school(): void
    {
        $this->assertSame('L$ 1,234.56', Money::format(123456));

        $this->settings()->updateGroup($this->school->id, 'finance', ['finance_currency' => 'US$']);

        // A fresh School instance, as the next request would build.
        app(SchoolContext::class)->setSchool(School::find($this->school->id));

        $this->assertSame('US$ 1,234.56', Money::format(123456));
        $this->assertSame('US$ 1.2k', Money::compact(123456));
    }

    public function test_each_school_keeps_its_own_currency(): void
    {
        $other = $this->createSchool(['name' => 'Another School']);

        $this->settings()->updateGroup($this->school->id, 'finance', ['finance_currency' => 'US$']);

        app(SchoolContext::class)->setSchool(School::find($other->id));
        $this->assertSame('L$ 100.00', Money::format(10000));

        app(SchoolContext::class)->setSchool(School::find($this->school->id));
        $this->assertSame('US$ 100.00', Money::format(10000));
    }

    public function test_a_list_setting_never_saves_as_empty(): void
    {
        // An empty list would leave the public application form with no upload
        // slots at all, so the catalogue default stands instead.
        $this->settings()->updateGroup($this->school->id, 'admissions', [
            'admission_document_types' => "   \n  \n",
        ]);

        $this->assertSame(
            SchoolSettings::catalogue()['admission_document_types']['default'],
            $this->settings()->get('admission_document_types'),
        );
    }

    public function test_the_document_list_is_split_by_line_and_deduplicated(): void
    {
        $this->settings()->updateGroup($this->school->id, 'admissions', [
            'admission_document_types' => "Birth certificate\n  Transcript  \nBirth certificate\n\nPhotograph",
        ]);

        $this->assertSame(
            ['Birth certificate', 'Transcript', 'Photograph'],
            $this->settings()->get('admission_document_types'),
        );
    }

    public function test_the_public_application_form_offers_the_documents_the_school_asked_for(): void
    {
        $this->settings()->updateGroup($this->school->id, 'admissions', [
            'admissions_open' => '1',
            'admission_document_types' => "Birth certificate\nBank statement",
        ]);

        $this->get(route('apply'))
            ->assertOk()
            ->assertSee('Bank statement');
    }

    public function test_closing_admissions_takes_the_form_down_and_refuses_a_submission(): void
    {
        $this->settings()->updateGroup($this->school->id, 'admissions', [
            'admissions_closed_message' => 'We reopen in March.',
        ]);

        $this->get(route('apply'))
            ->assertOk()
            ->assertSee('Applications are closed')
            ->assertSee('We reopen in March.');

        /*
         | And enforced on submit, not only by hiding the form: a stale tab or a
         | copied request must not get an application in after the school has
         | closed the intake.
         */
        $this->post(route('apply.store'), [
            'student_first_name' => 'Ada',
            'student_last_name' => 'Kollie',
            'guardian_name' => 'Mary Kollie',
            'guardian_phone' => '0770000000',
        ])->assertForbidden();

        $this->assertDatabaseCount('admissions', 0);
    }

    public function test_a_weekday_setting_never_saves_as_empty(): void
    {
        // No school days at all would divide by zero in every attendance
        // percentage on the platform.
        $this->settings()->updateGroup($this->school->id, 'academics', ['attendance_school_days' => []]);

        $this->assertSame([1, 2, 3, 4, 5], $this->settings()->get('attendance_school_days'));
    }

    public function test_settings_are_read_fresh_rather_than_memoised(): void
    {
        /*
         | The bug this guards against: a service that caches its values is
         | cached in turn on the controller Laravel keeps on the Route, so an
         | administrator saves a change and the site keeps serving the old value
         | until the process restarts.
         */
        $settings = $this->settings();

        $this->assertSame('L$', $settings->get('finance_currency'));

        SchoolSetting::updateOrCreate(
            ['school_id' => $this->school->id, 'key' => 'finance_currency'],
            ['value' => 'US$'],
        );

        $this->assertSame('US$', $settings->get('finance_currency'));
    }

    /* -------------------------------------------------------- settings hub */

    public function test_the_hub_warns_when_there_is_no_current_year_or_term(): void
    {
        $this->actingAs($this->administratorFor($this->school))
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('No academic year has been set up.');
    }

    public function test_the_hub_shows_where_the_school_currently_is(): void
    {
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

        $this->actingAs($this->administratorFor($this->school))
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('2026 / 2027')
            ->assertSee('First Term')
            ->assertDontSee('No academic year has been set up.');
    }
}
