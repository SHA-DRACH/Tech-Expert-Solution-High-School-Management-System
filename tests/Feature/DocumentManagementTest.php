<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Spec section 42: documents are stored privately and authorization-protected. */
class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected Student $student;

    protected Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->school = $this->createSchool();

        app(SchoolContext::class)->setSchool($this->school);

        $this->student = Student::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => 'Mary',
            'last_name' => 'Doe',
        ]);

        $this->teacher = Teacher::create([
            'school_id' => $this->school->id,
            'staff_number' => 'T-001',
            'first_name' => 'Grace',
            'last_name' => 'Kollie',
            'status' => 'active',
        ]);
    }

    protected function upload(array $overrides = [], ?\App\Models\User $as = null)
    {
        $user = $as ?? $this->userFor($this->school, ['students.view', 'students.update']);

        return $this->actingAs($user)->post(route('documents.store'), array_merge([
            'subject' => 'student',
            'subject_id' => $this->student->id,
            'title' => 'Birth certificate',
            'file' => UploadedFile::fake()->create('birth.pdf', 120, 'application/pdf'),
        ], $overrides));
    }

    public function test_a_document_is_filed_privately_and_starts_unverified(): void
    {
        $this->upload()->assertRedirect()->assertSessionHasNoErrors();

        $document = Document::firstOrFail();

        $this->assertSame('Birth certificate', $document->title);
        $this->assertSame('pending', $document->status);
        $this->assertSame(Student::class, $document->documentable_type);
        $this->assertSame($this->student->id, $document->documentable_id);

        // Private disk, not the publicly served one.
        Storage::disk('local')->assertExists($document->path);
        Storage::disk('public')->assertMissing($document->path);
    }

    public function test_filing_is_recorded_in_the_audit_trail(): void
    {
        $this->upload();

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Documents',
            'action' => 'uploaded',
        ]);
    }

    public function test_the_storage_path_is_never_written_to_the_audit_trail(): void
    {
        $this->upload();

        $document = Document::firstOrFail();

        foreach (\App\Models\AuditLog::all() as $entry) {
            $encoded = json_encode($entry->toArray());

            $this->assertStringNotContainsString($document->path, $encoded);
        }
    }

    public function test_a_document_cannot_be_filed_against_another_schools_student(): void
    {
        $otherSchool = $this->createSchool();

        $theirStudent = Student::factory()->create(['school_id' => $otherSchool->id]);

        $this->upload(['subject_id' => $theirStudent->id])->assertNotFound();

        $this->assertSame(0, Document::count());
    }

    public function test_verifying_records_who_checked_it(): void
    {
        $this->upload();

        $document = Document::firstOrFail();
        $verifier = $this->userFor($this->school, ['students.view', 'students.update']);

        $this->actingAs($verifier)
            ->patch(route('documents.verify', $document), [
                'status' => 'verified',
                'review_note' => 'Original sighted.',
            ])
            ->assertRedirect();

        $document->refresh();

        $this->assertSame('verified', $document->status);
        $this->assertSame($verifier->id, $document->verified_by);
        $this->assertNotNull($document->verified_at);
    }

    public function test_a_teacher_document_needs_the_teacher_permission_not_the_student_one(): void
    {
        // Only student permissions: filing against a teacher must be refused.
        $studentOnly = $this->userFor($this->school, ['students.view', 'students.update']);

        $this->actingAs($studentOnly)->post(route('documents.store'), [
            'subject' => 'teacher',
            'subject_id' => $this->teacher->id,
            'title' => 'Degree certificate',
            'file' => UploadedFile::fake()->create('degree.pdf', 80, 'application/pdf'),
        ])->assertRedirect();

        $document = Document::firstOrFail();

        // Filing is allowed for anyone who may update either kind of record,
        // but verifying a teacher document requires teachers.update.
        $this->actingAs($studentOnly)
            ->patch(route('documents.verify', $document), ['status' => 'verified'])
            ->assertForbidden();

        $teacherAdmin = $this->userFor($this->school, ['teachers.view', 'teachers.update']);

        $this->actingAs($teacherAdmin)
            ->patch(route('documents.verify', $document), ['status' => 'verified'])
            ->assertRedirect();
    }

    public function test_a_student_may_download_their_own_document(): void
    {
        $this->upload();

        $document = Document::firstOrFail();

        $studentUser = $this->userFor($this->school, []);
        $this->student->update(['user_id' => $studentUser->id]);

        $this->actingAs($studentUser->fresh())
            ->get(route('documents.download', $document))
            ->assertOk();
    }

    public function test_a_guardian_cleared_for_academics_may_download_their_childs_document(): void
    {
        $this->upload();

        $document = Document::firstOrFail();

        $guardianUser = $this->userFor($this->school, []);

        $guardian = Guardian::factory()->create([
            'school_id' => $this->school->id,
            'user_id' => $guardianUser->id,
        ]);

        $guardian->students()->attach($this->student, [
            'relationship' => 'Parent',
            'is_primary' => true,
            'can_view_academics' => true,
            'can_view_finance' => true,
        ]);

        $this->actingAs($guardianUser->fresh())
            ->get(route('documents.download', $document))
            ->assertOk();
    }

    public function test_an_unrelated_family_cannot_download_a_students_document(): void
    {
        $this->upload();

        $document = Document::firstOrFail();

        $strangerUser = $this->userFor($this->school, []);

        $stranger = Guardian::factory()->create([
            'school_id' => $this->school->id,
            'user_id' => $strangerUser->id,
        ]);

        // Linked to a different child entirely.
        $otherChild = Student::factory()->create(['school_id' => $this->school->id]);
        $stranger->students()->attach($otherChild, ['relationship' => 'Parent', 'is_primary' => true]);

        $this->actingAs($strangerUser->fresh())
            ->get(route('documents.download', $document))
            ->assertForbidden();
    }

    public function test_a_document_from_another_school_cannot_be_downloaded(): void
    {
        $otherSchool = $this->createSchool();

        $theirDocument = Document::withoutGlobalScopes()->create([
            'school_id' => $otherSchool->id,
            'documentable_type' => Student::class,
            'documentable_id' => $this->student->id,
            'title' => 'Theirs',
            'path' => 'documents/other/secret.pdf',
            'original_name' => 'secret.pdf',
            'status' => 'verified',
        ]);

        $administrator = $this->administratorFor($this->school);

        $this->actingAs($administrator)
            ->get(route('documents.download', $theirDocument))
            ->assertNotFound();
    }

    public function test_removing_a_document_keeps_the_record_recoverable(): void
    {
        $this->upload();

        $document = Document::firstOrFail();

        $this->actingAs($this->userFor($this->school, ['students.view', 'students.update']))
            ->delete(route('documents.destroy', $document))
            ->assertRedirect();

        // Soft-deleted, in keeping with the rule about not destroying history.
        $this->assertSame(0, Document::count());
        $this->assertSame(1, Document::withTrashed()->count());
    }

    public function test_an_expiry_date_must_follow_the_issue_date(): void
    {
        $this->upload([
            'issued_on' => now()->subYear()->toDateString(),
            'expires_on' => now()->subYears(2)->toDateString(),
        ])->assertSessionHasErrors('expires_on');
    }

    public function test_expiring_documents_are_flagged(): void
    {
        $this->upload(['expires_on' => now()->addWeeks(2)->toDateString()]);

        $document = Document::firstOrFail();

        $this->assertTrue($document->isExpiringSoon());
        $this->assertFalse($document->isExpired());

        $document->update(['expires_on' => now()->subDay()]);

        $this->assertTrue($document->fresh()->isExpired());
    }

    public function test_a_document_type_in_use_cannot_be_removed(): void
    {
        $type = DocumentType::create([
            'school_id' => $this->school->id,
            'name' => 'Medical record',
            'applies_to' => 'students',
        ]);

        $this->upload(['document_type_id' => $type->id]);

        $this->actingAs($this->administratorFor($this->school))
            ->delete(route('documents.types.destroy', $type))
            ->assertSessionHasErrors('document_type');

        $this->assertDatabaseHas('document_types', ['id' => $type->id]);
    }

    public function test_an_unused_document_type_can_be_removed(): void
    {
        $type = DocumentType::create([
            'school_id' => $this->school->id,
            'name' => 'Unused type',
            'applies_to' => 'general',
        ]);

        $this->actingAs($this->administratorFor($this->school))
            ->delete(route('documents.types.destroy', $type))
            ->assertRedirect();

        $this->assertDatabaseMissing('document_types', ['id' => $type->id]);
    }
}
