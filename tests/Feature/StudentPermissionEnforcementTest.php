<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentPermission;
use App\Models\User;
use App\Support\Money;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec section 24: the administrator controls student access from the backend.
 *
 * A switch that changes nothing is worse than no switch at all — it tells an
 * administrator they have restricted something when they have not. Three of the
 * twelve abilities were in exactly that state: `view_profile`, `edit_profile`
 * and `view_fees` appeared on the permissions screen and were read by no code
 * anywhere. `view_fees` had no student fees page behind it at all.
 *
 * These tests assert every ability is enforced, so a new one cannot be added to
 * the catalogue and left decorative.
 */
class StudentPermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected Student $student;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();
        app(SchoolContext::class)->setSchool($this->school);

        $year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026 / 2027',
            'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_current' => true,
        ]);

        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 9', 'level' => 9]);
        $section = Section::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => 'A']);

        $this->student = Student::create([
            'school_id' => $this->school->id, 'student_number' => 'S-1',
            'first_name' => 'Ada', 'last_name' => 'Doe', 'status' => 'active',
        ]);

        Enrollment::create([
            'school_id' => $this->school->id, 'student_id' => $this->student->id,
            'academic_year_id' => $year->id, 'school_class_id' => $class->id,
            'section_id' => $section->id, 'status' => 'active',
        ]);

        $this->user = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $this->student->update(['user_id' => $this->user->id]);
    }

    protected function set(string $ability, bool $allowed): void
    {
        StudentPermission::updateOrCreate(
            ['school_id' => $this->school->id, 'student_id' => $this->student->id, 'ability' => $ability],
            ['allowed' => $allowed],
        );
    }

    /* ------------------------------------------------------- edit_profile */

    public function test_a_student_cannot_change_their_details_unless_allowed(): void
    {
        // Defaults to off, which matches the spec's own example.
        $this->actingAs($this->user)
            ->put(route('profile.update'), ['name' => 'Renamed', 'email' => 'renamed@example.test'])
            ->assertForbidden();

        $this->assertNotSame('Renamed', $this->user->fresh()->name);

        $this->set('edit_profile', true);

        $this->actingAs($this->user)
            ->put(route('profile.update'), ['name' => 'Renamed', 'email' => 'renamed@example.test'])
            ->assertRedirect();

        $this->assertSame('Renamed', $this->user->fresh()->name);
    }

    public function test_the_details_form_is_not_offered_when_editing_is_off(): void
    {
        $html = $this->actingAs($this->user)->get(route('profile.edit'))->assertOk()->getContent();

        // Read-only rather than hidden: a student should still be able to check
        // the school has their name right.
        $this->assertStringContainsString($this->user->name, $html);
        $this->assertStringNotContainsString('Save details', $html);

        $this->set('edit_profile', true);

        $html = $this->actingAs($this->user)->get(route('profile.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('Save details', $html);
    }

    public function test_a_student_can_always_change_their_own_password(): void
    {
        $this->user->update(['password' => 'the-old-password']);

        /*
         | Never gated. Being unable to change your own password is an
         | account-security problem, not a profile preference, and no school
         | setting should be able to create one.
         */
        $this->set('edit_profile', false);
        $this->set('view_profile', false);

        $this->actingAs($this->user)
            ->put(route('profile.password'), [
                'current_password' => 'the-old-password',
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('a-brand-new-password', $this->user->fresh()->password));
    }

    /* ------------------------------------------------------- view_profile */

    public function test_hiding_the_profile_still_leaves_the_password_form(): void
    {
        $this->set('view_profile', false);

        /*
         | Asserted on the details card, not on the email string. The account
         | menu in the header shows the signed-in address on every page, and it
         | should: a person needs to know which account they are in. This
         | permission governs the profile module, not that.
         */
        $this->actingAs($this->user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('has not made your details visible')
            ->assertDontSee('Save details')
            ->assertSee('Change password');
    }

    /* ---------------------------------------------------------- view_fees */

    public function test_fees_are_hidden_until_the_school_allows_them(): void
    {
        Invoice::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'academic_year_id' => AcademicYear::first()->id,
            'invoice_number' => 'INV-0001',
            'issued_on' => now()->subWeek(),
            'total_minor' => Money::toMinor(15000),
            'status' => 'issued',
        ]);

        // Defaults to off: many schools would rather discuss money with the
        // parent than with the child.
        $this->actingAs($this->user)->get(route('student.fees'))->assertForbidden();

        $this->set('view_fees', true);

        $this->actingAs($this->user)
            ->get(route('student.fees'))
            ->assertOk()
            ->assertSee('INV-0001')
            ->assertSee('15,000.00');
    }

    public function test_a_student_sees_only_their_own_invoices(): void
    {
        $this->set('view_fees', true);

        $other = Student::create([
            'school_id' => $this->school->id, 'student_number' => 'S-9',
            'first_name' => 'Someone', 'last_name' => 'Else', 'status' => 'active',
        ]);

        Invoice::create([
            'school_id' => $this->school->id, 'student_id' => $other->id,
            'academic_year_id' => AcademicYear::first()->id,
            'invoice_number' => 'INV-THEIRS', 'issued_on' => now(),
            'total_minor' => Money::toMinor(9000), 'status' => 'issued',
        ]);

        $this->actingAs($this->user)
            ->get(route('student.fees'))
            ->assertOk()
            ->assertDontSee('INV-THEIRS');
    }

    public function test_the_fees_link_appears_only_when_fees_are_visible(): void
    {
        $html = $this->actingAs($this->user)->get(route('student.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString(route('student.fees'), $html);

        $this->set('view_fees', true);

        $html = $this->actingAs($this->user)->get(route('student.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString(route('student.fees'), $html);
    }

    /* ------------------------------------------- nothing left decorative */

    public function test_every_ability_in_the_catalogue_is_read_by_some_code(): void
    {
        /*
         | The guard rail. `view_profile`, `edit_profile` and `view_fees` each
         | sat in this catalogue, showed a toggle to the administrator, and were
         | consulted by nothing — so switching them changed nothing at all.
         | This fails the build if a new ability is added and left that way.
         */
        $sources = collect(
            array_merge(
                glob(app_path('**/*.php')),
                glob(app_path('**/**/*.php')),
                glob(app_path('**/**/**/*.php')),
                glob(resource_path('views/**/*.blade.php')),
                glob(resource_path('views/**/**/*.blade.php')),
            )
        )
            ->reject(fn (string $path) => str_ends_with($path, 'StudentPermission.php'))
            ->map(fn (string $path) => file_get_contents($path))
            ->implode("\n");

        $unused = collect(array_keys(StudentPermission::ABILITIES))
            ->reject(fn (string $ability) => str_contains($sources, "'{$ability}'"))
            ->values()
            ->all();

        $this->assertSame([], $unused,
            'These student permissions are shown to administrators but read by no code, so switching them does nothing.');
    }
}
