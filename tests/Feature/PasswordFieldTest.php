<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Every password box can be revealed.
 *
 * The toggle lives in `x-ui.input` rather than at each call site, so a new
 * password field gets it without anyone remembering to ask. These tests hold
 * that line: one checks the component itself, the rest walk the actual pages a
 * password is typed on.
 */
class PasswordFieldTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();

        app(SchoolContext::class)->setSchool($this->school);
    }

    /** How many revealable password boxes a page renders. */
    protected function toggleCount(string $html): int
    {
        return substr_count($html, 'x-bind:type="show ? \'text\' : \'password\'"');
    }

    /**
     * Render a component on its own.
     *
     * `$errors` is normally shared by ShareErrorsFromSession, which does not
     * run for a bare `Blade::render()`, so it is supplied here rather than
     * having the component defend against a variable that always exists in a
     * real request.
     */
    protected function render(string $template): string
    {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        return Blade::render($template);
    }

    public function test_a_password_input_renders_a_reveal_toggle(): void
    {
        $html = $this->render('<x-ui.input name="password" type="password" />');

        $this->assertStringContainsString('type="password"', $html,
            'The plain attribute must stay `password` for a browser with no JavaScript.');

        $this->assertStringContainsString('x-bind:type="show ? \'text\' : \'password\'"', $html);
        $this->assertStringContainsString('Show password', $html);
        $this->assertStringContainsString('Hide password', $html);

        // Room for the icon, so typed text cannot run underneath it.
        $this->assertStringContainsString('pr-11', $html);

        // type="button", or clicking the eye would submit the form.
        $this->assertStringContainsString('type="button"', $html);

        /*
         | x-cloak on the button: until Alpine is running it is not rendered at
         | all. A visible control that cannot do anything is worse than no
         | control, and without JavaScript this one could reveal nothing.
         */
        $this->assertStringContainsString('x-cloak', $html);
    }

    public function test_an_ordinary_input_gets_no_toggle(): void
    {
        $html = $this->render('<x-ui.input name="first_name" />');

        $this->assertSame(0, $this->toggleCount($html));
        $this->assertStringNotContainsString('Show password', $html);
        $this->assertStringNotContainsString('pr-11', $html);
    }

    public function test_each_box_toggles_on_its_own(): void
    {
        $html = $this->render(
            '<x-ui.input name="password" type="password" /><x-ui.input name="password_confirmation" type="password" />'
        );

        /*
         | Two separate x-data scopes. One shared scope would reveal every box
         | on the page at once - including, on the profile screen, the current
         | password someone only meant to check their new one against.
         */
        $this->assertSame(2, substr_count($html, 'x-data="{ show: false }"'));
    }

    public function test_the_sign_in_page_has_one(): void
    {
        $this->assertSame(1, $this->toggleCount($this->get(route('login'))->assertOk()->getContent()));
    }

    public function test_creating_an_account_has_one_on_both_boxes(): void
    {
        $html = $this->actingAs($this->userFor($this->school, ['users.view', 'users.create']))
            ->get(route('users.create'))
            ->assertOk()
            ->getContent();

        $this->assertSame(2, $this->toggleCount($html));
    }

    public function test_editing_an_account_has_one_on_both_boxes(): void
    {
        $account = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        // A role with no staff permissions, so the editor does not out-rank it.
        $account->roles()->attach(Role::inCurrentSchool()->where('slug', 'parent-guardian')->value('id'));

        $html = $this->actingAs($this->userFor($this->school, ['users.view', 'users.update']))
            ->get(route('users.edit', $account))
            ->assertOk()
            ->getContent();

        $this->assertSame(2, $this->toggleCount($html));
    }

    public function test_my_account_has_one_on_all_three_boxes(): void
    {
        $user = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);

        $html = $this->actingAs($user)->get(route('profile.edit'))->assertOk()->getContent();

        // Current password, new password, confirmation.
        $this->assertSame(3, $this->toggleCount($html));
    }

    public function test_giving_a_teacher_a_login_has_one_on_both_boxes(): void
    {
        Teacher::create([
            'school_id' => $this->school->id,
            'staff_number' => 'T-001',
            'first_name' => 'Emmanuel',
            'last_name' => 'Toe',
            'status' => 'active',
        ]);

        $html = $this->actingAs($this->userFor($this->school, ['teachers.view', 'users.create']))
            ->get(route('teachers.show', Teacher::first()))
            ->assertOk()
            ->getContent();

        $this->assertSame(2, $this->toggleCount($html));
    }

    public function test_no_password_box_anywhere_is_left_without_one(): void
    {
        /*
         | The backstop. Every password field in the application goes through
         | x-ui.input, and this fails the build if one is ever hand-rolled -
         | which is exactly how the eleventh field would quietly lose the
         | toggle the other ten have.
         */
        $handRolled = [];

        foreach (glob(resource_path('views/**/*.blade.php'), GLOB_BRACE) as $file) {
            $contents = file_get_contents($file);

            if (str_contains($contents, '<input') && preg_match('/<input\b(?![^>]*x-bind:type)[^>]*type="password"/', $contents)) {
                $handRolled[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file);
            }
        }

        $this->assertSame([], $handRolled,
            'These render a raw password input instead of <x-ui.input type="password">, so they have no reveal toggle.');
    }
}
