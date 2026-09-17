<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\DocumentCode;
use App\Models\SchoolSetting;
use App\Models\Student;

/**
 * Online services: public checks anyone can make without an account.
 *
 * Each must answer the question asked and nothing beside it, and a failed
 * lookup must not reveal which of the details was wrong.
 */
class OnlineServicesTest extends PeriodGradingTestCase
{
    protected Student $mary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPeriods();
        $this->mary = $this->student('GFI-2026-00001', 'Mary');

        // Setting up periods signs in an administrator; every check here is
        // made as an anonymous visitor.
        $this->app['auth']->forgetGuards();
    }

    public function test_the_page_is_public_and_lists_every_service(): void
    {
        $this->assertGuest();

        $this->get(route('online.index'))
            ->assertOk()
            ->assertSee('Check Application Status')
            ->assertSee('Verify a Student')
            ->assertSee('Verify a Document')
            ->assertSee('The school portal')
            ->assertSee('Paying fees')
            ->assertSee('Contact the office');
    }

    public function test_online_services_is_in_the_site_navigation(): void
    {
        $this->get(route('home'))->assertOk()->assertSee(route('online.index'));
    }

    /* ------------------------------------------------------------ student */

    public function test_a_student_can_be_verified_by_id(): void
    {
        $this->post(route('online.student'), ['by' => 'id', 'student_number' => 'GFI-2026-00001'])
            ->assertRedirect();

        $this->get(route('online.index'))
            ->assertSee('Verified student')
            ->assertSee('Mary D.')
            ->assertSee('Grade 9A')
            ->assertDontSee('Mary Doe');
    }

    public function test_a_student_can_be_verified_by_full_name_and_class(): void
    {
        $this->post(route('online.student'), ['by' => 'name', 'full_name' => '  mary   DOE ', 'section_id' => $this->section->id]);

        $this->get(route('online.index'))->assertSee('Verified student')->assertSee('Mary D.');
    }

    /** A name alone, or with the wrong class, confirms nothing. */
    public function test_the_right_name_in_the_wrong_class_is_not_found(): void
    {
        $other = \App\Models\Section::create(['school_id' => $this->school->id, 'school_class_id' => $this->section->school_class_id, 'name' => 'B']);

        $this->post(route('online.student'), ['by' => 'name', 'full_name' => 'Mary Doe', 'section_id' => $other->id]);

        $this->get(route('online.index'))->assertSee('No student matches those details')->assertDontSee('Mary D.');
    }

    public function test_a_partial_name_is_not_enough(): void
    {
        $this->post(route('online.student'), ['by' => 'name', 'full_name' => 'Mary', 'section_id' => $this->section->id]);

        $this->get(route('online.index'))->assertSee('No student matches those details');
    }

    public function test_a_former_student_is_shown_as_not_current(): void
    {
        $this->mary->update(['status' => 'graduated']);

        $this->post(route('online.student'), ['by' => 'id', 'student_number' => 'GFI-2026-00001'])
            ->assertSessionHas('studentResult', fn (array $result) => $result['current'] === false && $result['class'] === null);

        $this->get(route('online.index'))
            ->assertSee('Former or inactive student')
            ->assertSee('Graduated');
    }

    public function test_the_school_can_switch_off_checking_by_name(): void
    {
        SchoolSetting::create(['school_id' => $this->school->id, 'key' => 'online_lookup_by_name', 'value' => false]);

        $this->post(route('online.student'), ['by' => 'name', 'full_name' => 'Mary Doe', 'section_id' => $this->section->id])
            ->assertSessionHasErrors('full_name');

        $this->get(route('online.index'))->assertDontSee('Name &amp; class', false);
    }

    public function test_a_student_from_another_school_is_not_found(): void
    {
        $other = $this->createSchool();
        Student::withoutGlobalScopes()->create([
            'school_id' => $other->id, 'student_number' => 'OTHER-1', 'first_name' => 'Zed', 'last_name' => 'Kay', 'status' => 'active',
        ]);

        $this->post(route('online.student'), ['by' => 'id', 'student_number' => 'OTHER-1']);

        $this->get(route('online.index'))->assertSee('No student matches those details');
    }

    public function test_student_checks_are_rate_limited(): void
    {
        foreach (range(1, 10) as $i) {
            $this->post(route('online.student'), ['by' => 'id', 'student_number' => 'X-'.$i])->assertRedirect();
        }

        $this->post(route('online.student'), ['by' => 'id', 'student_number' => 'X-11'])->assertStatus(429);
    }

    /* -------------------------------------------------------- application */

    protected function application(): Admission
    {
        return Admission::create([
            'school_id' => $this->school->id, 'application_number' => 'ADM-2026-00042', 'status' => 'documents_required',
            'student_first_name' => 'Ben', 'student_last_name' => 'Kollie', 'intended_class' => 'Grade 10',
            'guardian_name' => 'Ruth Kollie', 'guardian_phone' => '+231 77 123 4567',
        ]);
    }

    public function test_an_application_status_can_be_checked_with_number_and_phone(): void
    {
        $this->application();

        $this->post(route('online.application'), ['application_number' => 'ADM-2026-00042', 'phone' => '0771234567']);

        $this->get(route('online.index'))
            ->assertSee('Documents Required')
            ->assertSee('Ben K.')
            ->assertSee('The school needs more documents');
    }

    /** The application number alone is not enough, and the failure says nothing about which half was wrong. */
    public function test_a_wrong_phone_reveals_nothing(): void
    {
        $this->application();

        $this->post(route('online.application'), ['application_number' => 'ADM-2026-00042', 'phone' => '0779999999']);
        $wrongPhone = $this->get(route('online.index'))->assertDontSee('Ben K.')->assertSee('No application matches those details');

        $this->post(route('online.application'), ['application_number' => 'ADM-2026-99999', 'phone' => '0771234567']);
        $this->get(route('online.index'))->assertSee('No application matches those details');
    }

    /* ----------------------------------------------------------- document */

    protected function approvedGrade(float $score): void
    {
        $assessment = Assessment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id, 'term_id' => $this->period(1)->id,
            'section_id' => $this->section->id, 'subject_id' => $this->maths->id,
            'title' => 'Test', 'type' => 'period_test', 'max_score' => 100, 'weight' => 100, 'status' => 'approved',
        ]);
        AssessmentScore::create(['school_id' => $this->school->id, 'assessment_id' => $assessment->id, 'student_id' => $this->mary->id, 'score' => $score]);
    }

    public function test_grade_sheets_and_report_cards_carry_a_verification_code(): void
    {
        $staff = $this->userFor($this->school, ['reportcards.view']);

        $sheet = $this->actingAs($staff)->get(route('progress.grade-sheets', [
            'section' => $this->section->id, 'period' => $this->period(1)->id, 'student' => $this->mary->id,
        ]))->assertOk()->assertSee('Verification code');

        $card = $this->actingAs($staff)->get(route('progress.report-cards', ['section' => $this->section->id, 'student' => $this->mary->id]))
            ->assertOk()->assertSee('Verification code');

        $gs = DocumentCode::where('type', DocumentCode::GRADE_SHEET)->firstOrFail();
        $rc = DocumentCode::where('type', DocumentCode::REPORT_CARD)->firstOrFail();

        $this->assertMatchesRegularExpression('/^GS-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $gs->code);
        $this->assertMatchesRegularExpression('/^RC-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $rc->code);
        $sheet->assertSee($gs->code);
        $card->assertSee($rc->code);
    }

    /** Printing the same paper again gives the same code, so every copy checks alike. */
    public function test_reprinting_a_document_keeps_its_code(): void
    {
        $staff = $this->userFor($this->school, ['reportcards.view']);
        $url = route('progress.report-cards', ['section' => $this->section->id, 'student' => $this->mary->id]);

        $this->actingAs($staff)->get($url);
        $this->actingAs($staff)->get($url);

        $this->assertSame(1, DocumentCode::count());
    }

    public function test_a_code_shows_the_document_and_its_grades_to_whoever_holds_it(): void
    {
        $this->approvedGrade(87);
        $code = DocumentCode::for(DocumentCode::GRADE_SHEET, $this->mary, $this->year, $this->period(1));

        $this->post(route('online.document'), ['code' => strtolower(str_replace('-', ' ', $code->code))])
            ->assertRedirect(route('online.document.show', $code->code));

        $this->get(route('online.document.show', $code->code))
            ->assertOk()
            ->assertSee('Genuine document')
            ->assertSee('Mary Doe')
            ->assertSee('GFI-2026-00001')
            ->assertSee('Grade 9A')
            ->assertSee('1st period')
            ->assertSeeInOrder(['Mathematics', '87']);

        $this->assertSame(1, $code->fresh()->times_checked);
    }

    public function test_an_unknown_code_is_not_a_genuine_document(): void
    {
        $this->get(route('online.document.show', 'GS-AAAA-BBBB'))
            ->assertOk()
            ->assertSee('No document matches this code')
            ->assertDontSee('Genuine document');
    }

    /** Codes are random: nothing about a student lets anyone work one out. */
    public function test_codes_are_not_derived_from_the_student(): void
    {
        $first = DocumentCode::for(DocumentCode::REPORT_CARD, $this->mary, $this->year)->code;
        DocumentCode::query()->delete();
        $second = DocumentCode::for(DocumentCode::REPORT_CARD, $this->mary, $this->year)->code;

        $this->assertNotSame($first, $second);
        $this->assertStringNotContainsString('00001', $first);
    }

    public function test_another_schools_code_does_not_verify_here(): void
    {
        $other = $this->createSchool();
        $foreignStudent = Student::withoutGlobalScopes()->create([
            'school_id' => $other->id, 'student_number' => 'OTHER-1', 'first_name' => 'Zed', 'last_name' => 'Kay', 'status' => 'active',
        ]);
        $foreignYear = \App\Models\AcademicYear::withoutGlobalScopes()->create([
            'school_id' => $other->id, 'name' => 'X', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        DocumentCode::withoutGlobalScopes()->create([
            'school_id' => $other->id, 'code' => 'RC-ZZZZ-ZZZZ', 'type' => 'report-card',
            'student_id' => $foreignStudent->id, 'academic_year_id' => $foreignYear->id,
        ]);

        $this->get(route('online.document.show', 'RC-ZZZZ-ZZZZ'))
            ->assertSee('No document matches this code')
            ->assertDontSee('Zed');
    }
}
