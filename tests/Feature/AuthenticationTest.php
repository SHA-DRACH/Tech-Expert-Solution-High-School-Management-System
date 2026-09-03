<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_page_is_reachable(): void
    {
        $this->createSchool();

        $this->get(route('login'))->assertOk()->assertSee('Sign in');
    }

    public function test_signing_in_hands_off_to_the_portal_router(): void
    {
        $school = $this->createSchool();
        $user = $this->userFor($school, ['dashboard.view'], ['password' => Hash::make('correct-horse-battery')]);

        // Login always forwards to /portal, which decides the destination from
        // what the account actually is rather than from one permission.
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('portal'));

        $this->assertAuthenticatedAs($user);

        $this->actingAs($user)->get(route('portal'))->assertRedirect(route('dashboard'));
    }

    public function test_a_teacher_lands_on_the_teacher_portal_not_the_admin_dashboard(): void
    {
        $school = $this->createSchool();

        // Teachers hold dashboard.view, so routing on that permission alone
        // used to drop them on the administrator dashboard by mistake.
        $user = $this->userFor($school, ['dashboard.view', 'students.view']);

        \App\Models\Teacher::create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'staff_number' => 'TST-T-001',
            'first_name' => 'Test',
            'last_name' => 'Teacher',
            'status' => 'active',
        ]);

        $this->actingAs($user)->get(route('portal'))->assertRedirect(route('teaching.dashboard'));
    }

    public function test_a_portal_user_without_dashboard_access_reaches_the_portal_router(): void
    {
        $school = $this->createSchool();
        $user = $this->userFor($school, [], ['password' => Hash::make('correct-horse-battery')]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('portal'));
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $school = $this->createSchool();
        $user = $this->userFor($school, ['dashboard.view']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_suspended_account_cannot_sign_in(): void
    {
        $school = $this->createSchool();

        $user = $this->userFor($school, ['dashboard.view'], [
            'password' => Hash::make('correct-horse-battery'),
            'status' => 'suspended',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_account_suspended_mid_session_is_signed_out_on_its_next_request(): void
    {
        $school = $this->createSchool();
        $user = $this->userFor($school, ['dashboard.view']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->update(['status' => 'suspended']);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_guests_are_redirected_away_from_the_dashboard(): void
    {
        $this->createSchool();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_signing_in_is_recorded_in_the_audit_trail(): void
    {
        $school = $this->createSchool();
        $user = $this->userFor($school, ['dashboard.view'], ['password' => Hash::make('correct-horse-battery')]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'signed_in',
            'module' => 'Authentication',
        ]);
    }

    public function test_a_user_can_sign_out(): void
    {
        $school = $this->createSchool();
        $user = $this->userFor($school, ['dashboard.view']);

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
