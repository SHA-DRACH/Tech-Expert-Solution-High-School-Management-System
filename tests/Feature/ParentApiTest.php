<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Support\Money;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Spec section 64: the API the Flutter application will use.
 *
 * The point of these tests is that the API enforces exactly the same rules as
 * the web portal — it is not a second, looser way into the same data.
 */
class ParentApiTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();

        app(SchoolContext::class)->setSchool($this->school);

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026 / 2027',
            'is_current' => true,
        ]);

        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 9', 'level' => 9]);

        $this->section = Section::create([
            'school_id' => $this->school->id,
            'school_class_id' => $class->id,
            'name' => '9A',
        ]);
    }

    /** @return array{0: User, 1: Guardian, 2: Student} */
    protected function parentWithChild(array $pivot = []): array
    {
        $user = $this->userFor($this->school, [], ['password' => Hash::make('correct-horse-battery')]);

        $user->roles()->detach();
        $user->roles()->attach(
            Role::where('school_id', $this->school->id)->where('slug', 'parent-guardian')->firstOrFail()
        );

        $guardian = Guardian::factory()->create([
            'school_id' => $this->school->id,
            'user_id' => $user->id,
        ]);

        $student = Student::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => 'Mary',
            'last_name' => 'Doe',
        ]);

        Enrollment::create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->section->school_class_id,
            'section_id' => $this->section->id,
            'status' => 'active',
        ]);

        $guardian->students()->attach($student, array_merge([
            'relationship' => 'Parent',
            'is_primary' => true,
            'can_view_academics' => true,
            'can_view_finance' => true,
        ], $pivot));

        return [$user->fresh(), $guardian, $student];
    }

    protected function tokenFor(User $user): string
    {
        return $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
            'device_name' => 'Test phone',
        ])->json('token');
    }

    /* -------------------------------------------------------------- auth */

    public function test_a_parent_signs_in_and_receives_a_token(): void
    {
        [$user] = $this->parentWithChild();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
            'device_name' => 'Test phone',
        ])->assertOk();

        $this->assertNotEmpty($response->json('token'));
        $this->assertSame('parent', $response->json('user.portal'));
        $this->assertSame($this->school->name, $response->json('user.school.name'));
    }

    public function test_wrong_credentials_are_refused_without_revealing_which_half_was_wrong(): void
    {
        [$user] = $this->parentWithChild();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'Test phone',
        ])->assertStatus(422);

        $message = $response->json('errors.email.0');

        $this->assertSame('The credentials provided do not match our records.', $message);

        // The same message for an address that does not exist at all.
        $this->postJson('/api/login', [
            'email' => 'nobody@example.test',
            'password' => 'wrong-password',
            'device_name' => 'Test phone',
        ])->assertStatus(422)->assertJsonPath('errors.email.0', $message);
    }

    public function test_a_suspended_account_cannot_get_a_token(): void
    {
        [$user] = $this->parentWithChild();

        $user->update(['status' => 'suspended']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
            'device_name' => 'Test phone',
        ])->assertStatus(422);
    }

    public function test_the_endpoints_are_closed_without_a_token(): void
    {
        $this->getJson('/api/parent/children')->assertUnauthorized();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_signing_out_revokes_only_the_calling_token(): void
    {
        [$user] = $this->parentWithChild();

        $this->tokenFor($user);
        $phone = $this->tokenFor($user);

        $this->assertSame(2, $user->tokens()->count());

        $this->withToken($phone)->postJson('/api/logout')->assertOk();

        /*
         | Asserted against the token table rather than by replaying the
         | revoked token: Laravel's auth guard caches the resolved user for the
         | lifetime of the application instance, and a test reuses one instance
         | across requests, so a replay would wrongly appear to succeed. Each
         | real request boots its own instance and re-checks the token.
         */
        $this->assertSame(1, $user->fresh()->tokens()->count());

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => explode('|', $phone)[0],
        ]);
    }

    /* ------------------------------------------------------------ portal */

    public function test_a_parent_sees_only_their_own_children(): void
    {
        [$user] = $this->parentWithChild();

        // Another family's child in the same school.
        Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Unrelated']);

        $response = $this->withToken($this->tokenFor($user))
            ->getJson('/api/parent/children')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Mary Doe', $response->json('data.0.name'));
    }

    public function test_a_child_belonging_to_another_family_is_not_found(): void
    {
        [$user] = $this->parentWithChild();
        [, , $otherChild] = $this->parentWithChild();

        $this->withToken($this->tokenFor($user))
            ->getJson("/api/parent/children/{$otherChild->id}")
            ->assertNotFound();
    }

    public function test_a_child_in_another_school_is_not_found(): void
    {
        [$user] = $this->parentWithChild();

        $otherSchool = $this->createSchool();
        $theirChild = Student::factory()->create(['school_id' => $otherSchool->id]);

        $this->withToken($this->tokenFor($user))
            ->getJson("/api/parent/children/{$theirChild->id}")
            ->assertNotFound();
    }

    public function test_the_academic_flag_is_honoured_over_the_api(): void
    {
        [$user, , $child] = $this->parentWithChild(['can_view_academics' => false]);

        $this->withToken($this->tokenFor($user))
            ->getJson("/api/parent/children/{$child->id}/grades")
            ->assertForbidden();
    }

    public function test_the_finance_flag_is_honoured_over_the_api(): void
    {
        [$user, , $child] = $this->parentWithChild(['can_view_finance' => false]);

        $this->withToken($this->tokenFor($user))
            ->getJson("/api/parent/children/{$child->id}/fees")
            ->assertForbidden();

        // And the summary hides the figure rather than leaking it.
        $this->withToken($this->tokenFor($user))
            ->getJson("/api/parent/children/{$child->id}")
            ->assertOk()
            ->assertJsonPath('data.outstanding', null);
    }

    public function test_fees_are_returned_with_both_a_raw_and_a_formatted_amount(): void
    {
        [$user, , $child] = $this->parentWithChild();

        Invoice::create([
            'school_id' => $this->school->id,
            'student_id' => $child->id,
            'academic_year_id' => $this->year->id,
            'invoice_number' => 'INV-2026-00001',
            'issued_on' => now(),
            'total_minor' => Money::toMinor(15000),
            'status' => 'issued',
        ]);

        $response = $this->withToken($this->tokenFor($user))
            ->getJson("/api/parent/children/{$child->id}/fees")
            ->assertOk();

        $this->assertSame(Money::toMinor(15000), $response->json('meta.outstanding.minor'));
        $this->assertSame('L$ 15,000.00', $response->json('meta.outstanding.formatted'));
    }

    public function test_a_staff_account_cannot_use_the_parent_endpoints(): void
    {
        $staff = $this->userFor($this->school, ['students.view'], [
            'password' => Hash::make('correct-horse-battery'),
        ]);

        $this->withToken($this->tokenFor($staff))
            ->getJson('/api/parent/children')
            ->assertForbidden();
    }

    public function test_notifications_are_scoped_to_the_calling_account(): void
    {
        [$user] = $this->parentWithChild();
        [$otherUser] = $this->parentWithChild();

        $otherUser->notify(new \App\Notifications\AnnouncementPublished(
            \App\Models\Announcement::create([
                'school_id' => $this->school->id,
                'title' => 'Not for you',
                'body' => 'Private.',
                'category' => 'general',
                'audience' => ['parents'],
                'published_at' => now(),
                'status' => 'published',
            ])
        ));

        $response = $this->withToken($this->tokenFor($user))
            ->getJson('/api/parent/notifications')
            ->assertOk();

        $this->assertCount(0, $response->json('data'));
    }
}
