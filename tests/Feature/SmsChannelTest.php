<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Notifications\AttendanceAlert;
use App\Services\Sms\SmsGateway;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec section 33: the SMS side of attendance notifications.
 *
 * No real provider is wired in — choosing one is the school's decision — so
 * these tests verify the seam a provider plugs into: opt-in, number
 * normalisation, and that a failing gateway never breaks the action that
 * triggered it.
 */
class SmsChannelTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AttendanceRecord $record;

    protected \App\Models\User $guardianUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool(['short_name' => 'Grace Foundation']);

        app(SchoolContext::class)->setSchool($this->school);

        $year = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026 / 2027',
            'is_current' => true,
        ]);

        $student = Student::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => 'Mary',
            'last_name' => 'Doe',
        ]);

        $this->guardianUser = $this->userFor($this->school, []);

        $guardian = Guardian::factory()->create([
            'school_id' => $this->school->id,
            'user_id' => $this->guardianUser->id,
            'phone' => '077 000 1234',
        ]);

        $guardian->students()->attach($student, [
            'relationship' => 'Parent',
            'is_primary' => true,
            'can_view_academics' => true,
            'can_view_finance' => true,
        ]);

        $this->record = AttendanceRecord::create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'recorded_on' => now()->toDateString(),
            'status' => 'absent',
        ]);
    }

    /** A gateway that records what it was asked to send. */
    protected function spyGateway(): object
    {
        $spy = new class implements SmsGateway
        {
            public array $sent = [];

            public function send(string $to, string $message): bool
            {
                $this->sent[] = ['to' => $to, 'message' => $message];

                return true;
            }
        };

        $this->app->instance(SmsGateway::class, $spy);

        return $spy;
    }

    public function test_nothing_is_sent_while_sms_is_switched_off(): void
    {
        config(['sms.enabled' => false]);

        $spy = $this->spyGateway();

        $this->guardianUser->notify(new AttendanceAlert($this->record));

        $this->assertSame([], $spy->sent);

        // The in-app notification still arrives.
        $this->assertSame(1, $this->guardianUser->notifications()->count());
    }

    public function test_a_text_is_sent_once_a_gateway_is_connected(): void
    {
        config(['sms.enabled' => true]);

        $spy = $this->spyGateway();

        $this->guardianUser->notify(new AttendanceAlert($this->record));

        $this->assertCount(1, $spy->sent);
        $this->assertStringContainsString('Mary', $spy->sent[0]['message']);
        $this->assertStringContainsString('absent', $spy->sent[0]['message']);
        $this->assertStringContainsString('Grace Foundation', $spy->sent[0]['message']);
    }

    public function test_a_local_number_is_expanded_to_international_format(): void
    {
        config(['sms.enabled' => true, 'sms.default_country_code' => '231']);

        $spy = $this->spyGateway();

        $this->guardianUser->notify(new AttendanceAlert($this->record));

        // "077 000 1234" is local: the trunk zero goes, the country code arrives.
        $this->assertSame('+231770001234', $spy->sent[0]['to']);
    }

    public function test_a_number_already_in_international_format_is_left_alone(): void
    {
        config(['sms.enabled' => true]);

        Guardian::where('user_id', $this->guardianUser->id)->update(['phone' => '+231 88 555 0000']);

        $spy = $this->spyGateway();

        $this->guardianUser->fresh()->notify(new AttendanceAlert($this->record));

        $this->assertSame('+231885550000', $spy->sent[0]['to']);
    }

    public function test_a_guardian_with_no_phone_number_is_skipped_quietly(): void
    {
        config(['sms.enabled' => true]);

        Guardian::where('user_id', $this->guardianUser->id)->update(['phone' => null]);

        $spy = $this->spyGateway();

        $this->guardianUser->fresh()->notify(new AttendanceAlert($this->record));

        $this->assertSame([], $spy->sent);
        $this->assertSame(1, $this->guardianUser->notifications()->count());
    }

    public function test_a_failing_gateway_does_not_break_the_notification(): void
    {
        config(['sms.enabled' => true]);

        $this->app->instance(SmsGateway::class, new class implements SmsGateway
        {
            public function send(string $to, string $message): bool
            {
                throw new \RuntimeException('Provider unreachable');
            }
        });

        // The exception is caught, so recording attendance still succeeds.
        $this->guardianUser->notify(new AttendanceAlert($this->record));

        $this->assertSame(1, $this->guardianUser->notifications()->count());
    }

    public function test_the_default_gateway_logs_rather_than_pretending_to_send(): void
    {
        // Nothing is configured out of the box, so the log gateway is bound.
        $this->assertInstanceOf(
            \App\Services\Sms\LogSmsGateway::class,
            $this->app->make(SmsGateway::class)
        );
    }
}
