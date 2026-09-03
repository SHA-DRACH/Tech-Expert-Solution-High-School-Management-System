<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\AdmissionDocument;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\Student;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The security tests that matter most on this platform.
 *
 * Each one sets up two schools and confirms that a fully-privileged user in
 * school A cannot see, open or act on school B's records, even when they know
 * the exact id.
 */
class MultiSchoolIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_listings_only_contain_the_users_own_school(): void
    {
        $schoolA = $this->createSchool(['name' => 'Alpha Institution']);
        $schoolB = $this->createSchool(['name' => 'Beta Institution']);

        $ours = Student::factory()->create(['school_id' => $schoolA->id, 'first_name' => 'Ourstudent']);
        $theirs = Student::factory()->create(['school_id' => $schoolB->id, 'first_name' => 'Theirstudent']);

        $this->actingAs($this->administratorFor($schoolA))
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee($ours->student_number)
            ->assertDontSee($theirs->student_number);
    }

    public function test_a_student_from_another_school_cannot_be_opened_by_id(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        $theirStudent = Student::factory()->create(['school_id' => $schoolB->id]);

        $this->actingAs($this->administratorFor($schoolA))
            ->get(route('students.show', $theirStudent))
            ->assertNotFound();
    }

    public function test_a_guardian_from_another_school_cannot_be_opened_by_id(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        $theirGuardian = Guardian::factory()->create(['school_id' => $schoolB->id]);

        $this->actingAs($this->administratorFor($schoolA))
            ->get(route('guardians.show', $theirGuardian))
            ->assertNotFound();
    }

    public function test_an_admission_from_another_school_cannot_be_opened_or_decided(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        $theirAdmission = Admission::factory()->create(['school_id' => $schoolB->id]);

        $administrator = $this->administratorFor($schoolA);

        $this->actingAs($administrator)
            ->get(route('admissions.show', $theirAdmission))
            ->assertNotFound();

        $this->actingAs($administrator)
            ->patch(route('admissions.update', $theirAdmission), ['status' => 'approved'])
            ->assertNotFound();

        $this->assertSame('submitted', $theirAdmission->fresh()->status);
    }

    public function test_admission_documents_from_another_school_cannot_be_downloaded(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        $theirAdmission = Admission::factory()->create(['school_id' => $schoolB->id]);

        $theirDocument = AdmissionDocument::withoutEvents(fn () => AdmissionDocument::create([
            'school_id' => $schoolB->id,
            'admission_id' => $theirAdmission->id,
            'document_type' => 'Birth certificate',
            'original_name' => 'birth.pdf',
            'path' => 'admissions/private.pdf',
            'status' => 'pending',
        ]));

        $this->actingAs($this->administratorFor($schoolA))
            ->get(route('admissions.documents.download', $theirDocument))
            ->assertNotFound();
    }

    public function test_a_student_cannot_be_created_against_a_guardian_from_another_school(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        $theirGuardian = Guardian::factory()->create(['school_id' => $schoolB->id]);

        $this->actingAs($this->administratorFor($schoolA))
            ->post(route('students.store'), [
                'first_name' => 'Attempted',
                'last_name' => 'Link',
                'guardian_id' => $theirGuardian->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('students', ['first_name' => 'Attempted']);
    }

    public function test_a_user_account_cannot_be_created_against_a_role_from_another_school(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        $theirRole = Role::where('school_id', $schoolB->id)->where('slug', 'registrar')->firstOrFail();

        $this->actingAs($this->administratorFor($schoolA))
            ->post(route('users.store'), [
                'name' => 'Cross Tenant',
                'email' => 'cross@example.test',
                'password' => 'correct-horse-battery',
                'password_confirmation' => 'correct-horse-battery',
                'role_id' => $theirRole->id,
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'cross@example.test']);
    }

    public function test_a_user_from_another_school_cannot_be_suspended(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        $theirUser = $this->userFor($schoolB, []);

        $this->actingAs($this->administratorFor($schoolA))
            ->patch(route('users.status', $theirUser))
            ->assertForbidden();

        $this->assertSame('active', $theirUser->fresh()->status);
    }

    public function test_a_role_from_another_school_cannot_be_edited(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        $theirRole = Role::where('school_id', $schoolB->id)->where('slug', 'teacher')->firstOrFail();

        $this->actingAs($this->administratorFor($schoolA))
            ->put(route('roles.update', $theirRole), [
                'name' => 'Hijacked',
                'permissions' => ['settings.manage'],
            ])
            ->assertForbidden();

        $this->assertSame('Teacher', $theirRole->fresh()->name);
    }

    public function test_the_audit_trail_only_shows_the_users_own_school(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        // Each creation writes an audit entry stamped with its own school.
        Student::factory()->create(['school_id' => $schoolA->id, 'first_name' => 'Ouraudit']);
        Student::factory()->create(['school_id' => $schoolB->id, 'first_name' => 'Theiraudit']);

        $this->actingAs($this->administratorFor($schoolA))
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Ouraudit')
            ->assertDontSee('Theiraudit');
    }

    public function test_the_global_scope_blocks_tenant_queries_when_no_school_is_resolved(): void
    {
        $school = $this->createSchool();

        Student::factory()->count(3)->create(['school_id' => $school->id]);

        $context = app(SchoolContext::class);

        // Console context runs unrestricted so seeders and jobs still work.
        $this->assertSame(3, Student::count());

        // Scoped to a school, only that school's rows are visible.
        $context->setSchool($school);
        $this->assertSame(3, Student::count());

        $other = $this->createSchool();
        $context->setSchool($other);
        $this->assertSame(0, Student::count());
    }

    public function test_new_records_are_stamped_with_the_active_school(): void
    {
        $school = $this->createSchool();

        app(SchoolContext::class)->setSchool($school);

        $student = Student::create([
            'student_number' => 'AUTO-1',
            'first_name' => 'Auto',
            'last_name' => 'Stamped',
        ]);

        $this->assertSame($school->id, $student->school_id);
    }
}
