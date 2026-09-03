<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_administrator_sees_every_school(): void
    {
        $alpha = $this->createSchool(['name' => 'Alpha Institution']);
        $beta = $this->createSchool(['name' => 'Beta Institution']);

        $this->actingAs($this->superAdministrator())
            ->get(route('platform.schools'))
            ->assertOk()
            ->assertSee($alpha->name)
            ->assertSee($beta->name);
    }

    public function test_a_school_administrator_cannot_reach_the_platform_school_list(): void
    {
        $school = $this->createSchool();

        $this->actingAs($this->administratorFor($school))
            ->get(route('platform.schools'))
            ->assertForbidden();
    }

    public function test_entering_a_school_scopes_the_super_administrator_to_it(): void
    {
        $alpha = $this->createSchool(['name' => 'Alpha Institution']);
        $beta = $this->createSchool(['name' => 'Beta Institution']);

        Student::factory()->create(['school_id' => $alpha->id, 'first_name' => 'Alphastudent']);
        Student::factory()->create(['school_id' => $beta->id, 'first_name' => 'Betastudent']);

        $platformAdmin = $this->superAdministrator();

        $this->actingAs($platformAdmin)
            ->post(route('platform.schools.select', $alpha))
            ->assertRedirect(route('dashboard'));

        // Inside Alpha's workspace, Beta's students are out of reach even for a
        // super administrator.
        $this->actingAs($platformAdmin)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Alphastudent')
            ->assertDontSee('Betastudent');
    }

    public function test_leaving_a_school_returns_to_platform_level(): void
    {
        $school = $this->createSchool();
        $platformAdmin = $this->superAdministrator();

        $this->actingAs($platformAdmin)->post(route('platform.schools.select', $school));

        $this->actingAs($platformAdmin)
            ->post(route('platform.schools.clear'))
            ->assertRedirect(route('platform.schools'));

        $this->assertNull(session('platform.active_school_id'));
    }

    public function test_selecting_a_school_is_recorded_in_the_audit_trail(): void
    {
        $school = $this->createSchool();
        $platformAdmin = $this->superAdministrator();

        $this->actingAs($platformAdmin)->post(route('platform.schools.select', $school));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $platformAdmin->id,
            'action' => 'school_selected',
            'module' => 'Platform',
        ]);
    }

    public function test_a_school_user_cannot_select_a_different_school(): void
    {
        $school = $this->createSchool();
        $other = $this->createSchool();

        $this->actingAs($this->administratorFor($school))
            ->post(route('platform.schools.select', $other))
            ->assertForbidden();
    }
}
