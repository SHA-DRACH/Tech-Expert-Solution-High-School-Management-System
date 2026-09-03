<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Managing user accounts and the roles they hold (spec sections 12 to 15).
 *
 * The rule these protect is that an account's roles are the only thing that
 * decides what it can reach, so a role id arriving from a form is re-resolved
 * against the current school every time rather than trusted.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();

        app(SchoolContext::class)->setSchool($this->school);
    }

    protected function manager(): User
    {
        return $this->userFor($this->school, ['users.view', 'users.create', 'users.update', 'users.suspend']);
    }

    protected function account(array $attributes = []): User
    {
        return User::factory()->create(['school_id' => $this->school->id] + $attributes);
    }

    protected function role(string $name): Role
    {
        return Role::inCurrentSchool()->where('name', $name)->firstOrFail();
    }

    /* -------------------------------------------------------------- listing */

    public function test_the_listing_shows_every_account_with_the_roles_it_holds(): void
    {
        $teacher = $this->account(['name' => 'Grace Kollie']);
        $teacher->roles()->sync([$this->role('Teacher')->id]);

        $this->actingAs($this->manager())
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Grace Kollie')
            ->assertSee('Teacher');
    }

    public function test_the_listing_can_be_filtered_by_role(): void
    {
        $teacher = $this->account(['name' => 'Grace Kollie']);
        $teacher->roles()->sync([$this->role('Teacher')->id]);

        $bursar = $this->account(['name' => 'Joseph Weah']);
        $bursar->roles()->sync([$this->role('Accountant')->id]);

        $this->actingAs($this->manager())
            ->get(route('users.index', ['role' => $this->role('Teacher')->slug]))
            ->assertOk()
            ->assertSee('Grace Kollie')
            ->assertDontSee('Joseph Weah');
    }

    public function test_the_listing_can_be_filtered_by_status(): void
    {
        $this->account(['name' => 'Active Person', 'status' => 'active']);
        $this->account(['name' => 'Suspended Person', 'status' => 'suspended']);

        $this->actingAs($this->manager())
            ->get(route('users.index', ['status' => 'suspended']))
            ->assertOk()
            ->assertSee('Suspended Person')
            ->assertDontSee('Active Person');
    }

    public function test_an_account_in_another_school_is_never_listed(): void
    {
        $other = $this->createSchool(['name' => 'Another School']);

        User::factory()->create(['school_id' => $other->id, 'name' => 'Somebody Else']);

        $this->actingAs($this->manager())
            ->get(route('users.index'))
            ->assertOk()
            ->assertDontSee('Somebody Else');
    }

    /* ------------------------------------------------------------- viewing */

    public function test_the_detail_page_shows_what_the_account_can_actually_do(): void
    {
        $account = $this->account(['name' => 'Grace Kollie']);
        $account->roles()->sync([$this->role('Teacher')->id]);

        $this->actingAs($this->manager())
            ->get(route('users.show', $account))
            ->assertOk()
            ->assertSee('Grace Kollie')
            ->assertSee('Teacher')
            ->assertSee('What this account can do');
    }

    public function test_an_account_from_another_school_cannot_be_opened(): void
    {
        $other = $this->createSchool(['name' => 'Another School']);

        $foreign = User::factory()->create(['school_id' => $other->id]);

        $this->actingAs($this->manager())
            ->get(route('users.show', $foreign))
            ->assertForbidden();
    }

    /* ------------------------------------------------------------- editing */

    public function test_an_account_can_be_edited_and_its_roles_replaced(): void
    {
        $account = $this->account(['name' => 'Grace Kollie']);
        $account->roles()->sync([$this->role('Teacher')->id]);

        $this->actingAs($this->manager())
            ->put(route('users.update', $account), [
                'name' => 'Grace Kollie-Toe',
                'email' => 'grace.toe@example.test',
                'roles' => [$this->role('Accountant')->id],
            ])
            ->assertRedirect(route('users.show', $account));

        $account->refresh();

        $this->assertSame('Grace Kollie-Toe', $account->name);
        $this->assertSame('grace.toe@example.test', $account->email);
        $this->assertSame(['Accountant'], $account->roles->pluck('name')->all());

        $this->assertDatabaseHas('audit_logs', ['module' => 'Users', 'action' => 'updated']);
    }

    public function test_an_empty_password_box_leaves_the_password_alone(): void
    {
        $account = $this->account();
        $original = $account->password;

        $this->actingAs($this->manager())
            ->put(route('users.update', $account), [
                'name' => $account->name,
                'email' => $account->email,
                'roles' => [$this->role('Teacher')->id],
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect();

        $this->assertSame($original, $account->fresh()->password);
    }

    public function test_a_new_password_is_hashed_and_takes_effect(): void
    {
        $account = $this->account();

        $this->actingAs($this->manager())
            ->put(route('users.update', $account), [
                'name' => $account->name,
                'email' => $account->email,
                'roles' => [$this->role('Teacher')->id],
                'password' => 'a-long-enough-password',
                'password_confirmation' => 'a-long-enough-password',
            ])
            ->assertRedirect();

        $account->refresh();

        $this->assertNotSame('a-long-enough-password', $account->password);
        $this->assertTrue(Hash::check('a-long-enough-password', $account->password));
    }

    public function test_an_account_must_keep_at_least_one_role(): void
    {
        $account = $this->account();
        $account->roles()->sync([$this->role('Teacher')->id]);

        $this->actingAs($this->manager())
            ->put(route('users.update', $account), [
                'name' => $account->name,
                'email' => $account->email,
                'roles' => [],
            ])
            ->assertSessionHasErrors('roles');

        $this->assertCount(1, $account->fresh()->roles);
    }

    public function test_a_role_belonging_to_another_school_cannot_be_granted(): void
    {
        $other = $this->createSchool(['name' => 'Another School']);

        $foreignRole = Role::where('school_id', $other->id)->firstOrFail();

        $account = $this->account();

        // Section 59: an id from the frontend is re-resolved, never trusted.
        $this->actingAs($this->manager())
            ->put(route('users.update', $account), [
                'name' => $account->name,
                'email' => $account->email,
                'roles' => [$foreignRole->id],
            ])
            ->assertSessionHasErrors('roles');

        $this->assertCount(0, $account->fresh()->roles);
    }

    public function test_editing_an_account_needs_the_update_permission(): void
    {
        $account = $this->account();

        $viewer = $this->userFor($this->school, ['users.view']);

        $this->actingAs($viewer)->get(route('users.edit', $account))->assertForbidden();

        $this->actingAs($viewer)
            ->put(route('users.update', $account), [
                'name' => 'Renamed',
                'email' => 'renamed@example.test',
                'roles' => [$this->role('Teacher')->id],
            ])
            ->assertForbidden();
    }

    public function test_an_email_already_in_use_is_refused(): void
    {
        $taken = $this->account(['email' => 'taken@example.test']);
        $account = $this->account();

        $this->actingAs($this->manager())
            ->put(route('users.update', $account), [
                'name' => $account->name,
                'email' => 'taken@example.test',
                'roles' => [$this->role('Teacher')->id],
            ])
            ->assertSessionHasErrors('email');
    }

    /* -------------------------------------------------------------- export */

    public function test_the_account_list_exports_with_its_roles(): void
    {
        $account = $this->account(['name' => 'Grace Kollie', 'email' => 'grace@example.test']);
        $account->roles()->sync([$this->role('Teacher')->id]);

        $response = $this->actingAs($this->manager())
            ->get(route('exports.users'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $body = $response->streamedContent();

        $this->assertStringContainsString('Grace Kollie', $body);
        $this->assertStringContainsString('grace@example.test', $body);
        $this->assertStringContainsString('Teacher', $body);

        // Never the password hash, and never a token.
        $this->assertStringNotContainsString($account->password, $body);

        $this->assertDatabaseHas('audit_logs', ['module' => 'Users', 'action' => 'exported']);
    }

    public function test_the_export_honours_the_filters_the_screen_is_showing(): void
    {
        $this->account(['name' => 'Active Person', 'status' => 'active']);
        $this->account(['name' => 'Suspended Person', 'status' => 'suspended']);

        $body = $this->actingAs($this->manager())
            ->get(route('exports.users', ['status' => 'suspended']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Suspended Person', $body);
        $this->assertStringNotContainsString('Active Person', $body);
    }

    public function test_exporting_accounts_needs_the_view_permission(): void
    {
        $this->actingAs($this->userFor($this->school, ['dashboard.view']))
            ->get(route('exports.users'))
            ->assertForbidden();
    }
}
