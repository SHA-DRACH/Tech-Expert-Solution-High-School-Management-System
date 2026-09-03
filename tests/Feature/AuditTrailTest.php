<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Student;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_record_writes_an_entry(): void
    {
        $school = $this->createSchool();

        $student = Student::factory()->create(['school_id' => $school->id, 'first_name' => 'Audited']);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'auditable_type' => Student::class,
            'auditable_id' => $student->id,
            'action' => 'created',
            'module' => 'Students',
        ]);
    }

    public function test_an_update_records_what_changed(): void
    {
        $school = $this->createSchool();
        $student = Student::factory()->create(['school_id' => $school->id, 'status' => 'active']);

        $student->update(['status' => 'graduated']);

        $entry = AuditLog::where('auditable_id', $student->id)->where('action', 'updated')->firstOrFail();

        $this->assertSame('active', $entry->old_values['status']);
        $this->assertSame('graduated', $entry->new_values['status']);
    }

    public function test_an_update_that_changes_nothing_writes_no_entry(): void
    {
        $school = $this->createSchool();
        $student = Student::factory()->create(['school_id' => $school->id, 'status' => 'active']);

        $before = AuditLog::where('auditable_id', $student->id)->count();

        $student->update(['status' => 'active']);

        $this->assertSame($before, AuditLog::where('auditable_id', $student->id)->count());
    }

    public function test_the_acting_user_is_recorded(): void
    {
        $school = $this->createSchool();
        $administrator = $this->administratorFor($school);

        $this->actingAs($administrator)->post(route('guardians.store'), [
            'first_name' => 'Recorded',
            'last_name' => 'Guardian',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'user_name' => $administrator->name,
            'module' => 'Parents & guardians',
            'action' => 'created',
        ]);
    }

    public function test_passwords_never_reach_the_audit_trail(): void
    {
        $school = $this->createSchool();
        $administrator = $this->administratorFor($school);

        $this->actingAs($administrator)->post(route('users.store'), [
            'name' => 'New Staff',
            'email' => 'new.staff@example.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'role_id' => \App\Models\Role::where('school_id', $school->id)->where('slug', 'teacher')->value('id'),
        ]);

        $entries = AuditLog::where('module', 'Users')->get();

        $this->assertNotEmpty($entries);

        foreach ($entries as $entry) {
            $this->assertArrayNotHasKey('password', $entry->new_values ?? []);
            $this->assertArrayNotHasKey('password', $entry->old_values ?? []);
            $this->assertStringNotContainsString('correct-horse-battery', json_encode($entry->toArray()));
        }
    }

    public function test_audit_entries_cannot_be_modified(): void
    {
        $school = $this->createSchool();
        Student::factory()->create(['school_id' => $school->id]);

        $entry = AuditLog::firstOrFail();

        $this->expectException(RuntimeException::class);

        $entry->update(['description' => 'Rewritten history']);
    }

    public function test_audit_entries_cannot_be_deleted(): void
    {
        $school = $this->createSchool();
        Student::factory()->create(['school_id' => $school->id]);

        $entry = AuditLog::firstOrFail();

        $this->expectException(RuntimeException::class);

        $entry->delete();
    }

    public function test_settings_changes_are_recorded(): void
    {
        $school = $this->createSchool();
        $administrator = $this->administratorFor($school);

        $this->actingAs($administrator)->put(route('settings.school.update'), [
            'name' => 'Renamed Institution',
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Settings',
            'action' => 'updated',
            'school_id' => $school->id,
        ]);

        $this->assertSame('Renamed Institution', $school->fresh()->name);
    }
}
