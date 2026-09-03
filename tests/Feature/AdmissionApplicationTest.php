<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\AdmissionDocument;
use App\Services\ReferenceNumberGenerator;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdmissionApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_parent_can_open_the_public_application_form(): void
    {
        $school = $this->createSchool(['name' => 'Grace Foundation Institution']);

        $this->get(route('apply'))
            ->assertOk()
            ->assertSee('Apply for admission')
            ->assertSee($school->name);
    }

    public function test_an_application_is_submitted_and_given_an_application_number(): void
    {
        $this->createSchool();

        $response = $this->post(route('apply.store'), [
            'student_first_name' => 'Mary',
            'student_last_name' => 'Doe',
            'intended_class' => 'Grade 10',
            'guardian_name' => 'John Doe',
            'guardian_phone' => '+231770000000',
        ]);

        $response->assertRedirect(route('apply.complete'));

        $admission = Admission::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('submitted', $admission->status);
        $this->assertMatchesRegularExpression('/^ADM-\d{4}-\d{5}$/', $admission->application_number);
    }

    public function test_the_completion_page_is_not_reachable_without_a_submission(): void
    {
        $this->createSchool();

        // The application number lives in the session, so it cannot be guessed
        // from the URL the way a path parameter could.
        $this->get(route('apply.complete'))->assertNotFound();
    }

    public function test_an_application_requires_the_essential_fields(): void
    {
        $this->createSchool();

        $this->post(route('apply.store'), [])
            ->assertSessionHasErrors(['student_first_name', 'student_last_name', 'guardian_name', 'guardian_phone']);

        $this->assertSame(0, Admission::withoutGlobalScopes()->count());
    }

    public function test_uploaded_documents_are_stored_privately(): void
    {
        Storage::fake('local');

        $this->createSchool();

        $this->post(route('apply.store'), [
            'student_first_name' => 'Mary',
            'student_last_name' => 'Doe',
            'guardian_name' => 'John Doe',
            'guardian_phone' => '+231770000000',
            'documents' => [
                ['type' => 'Birth certificate', 'file' => UploadedFile::fake()->create('birth.pdf', 120, 'application/pdf')],
            ],
        ])->assertRedirect(route('apply.complete'));

        $document = AdmissionDocument::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('Birth certificate', $document->document_type);
        $this->assertSame('pending', $document->status);
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_application_numbers_stay_unique_per_school_and_do_not_reuse_after_deletion(): void
    {
        $school = $this->createSchool();
        $generator = app(ReferenceNumberGenerator::class);

        $first = $generator->applicationNumber($school);
        $second = $generator->applicationNumber($school);

        $this->assertNotSame($first, $second);

        // Counting rows would hand out a number already used; the sequence does not.
        Admission::factory()->create(['school_id' => $school->id, 'application_number' => $first])->delete();

        $third = $generator->applicationNumber($school);

        $this->assertNotSame($first, $third);
        $this->assertNotSame($second, $third);
    }

    public function test_student_numbers_use_the_schools_own_prefix(): void
    {
        $school = $this->createSchool(['student_number_prefix' => 'GFI']);

        $number = app(ReferenceNumberGenerator::class)->studentNumber($school);

        $this->assertStringStartsWith('GFI-'.now()->year.'-', $number);
    }

    public function test_a_registrar_can_move_an_application_through_review(): void
    {
        $school = $this->createSchool();
        $admission = Admission::factory()->create(['school_id' => $school->id]);

        $registrar = $this->userFor($school, ['admissions.view', 'admissions.review', 'admissions.approve']);

        $this->actingAs($registrar)
            ->patch(route('admissions.update', $admission), [
                'status' => 'approved',
                'review_notes' => 'Documents verified.',
            ])
            ->assertRedirect();

        $this->assertSame('approved', $admission->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $admission->id,
            'action' => 'status_changed',
            'module' => 'Admissions',
        ]);
    }

    public function test_approving_needs_its_own_permission_beyond_reviewing(): void
    {
        $school = $this->createSchool();
        $admission = Admission::factory()->create(['school_id' => $school->id]);

        $reviewer = $this->userFor($school, ['admissions.view', 'admissions.review']);

        $this->actingAs($reviewer)
            ->patch(route('admissions.update', $admission), ['status' => 'approved'])
            ->assertForbidden();

        $this->assertSame('submitted', $admission->fresh()->status);
    }

    public function test_verifying_a_document_records_who_reviewed_it(): void
    {
        $school = $this->createSchool();
        $admission = Admission::factory()->create(['school_id' => $school->id]);

        $document = app(SchoolContext::class)->for($school, fn () => AdmissionDocument::create([
            'admission_id' => $admission->id,
            'document_type' => 'Transfer certificate',
            'original_name' => 'transfer.pdf',
            'path' => 'admissions/transfer.pdf',
            'status' => 'pending',
        ]));

        $verifier = $this->userFor($school, ['admissions.view', 'admissions.documents.verify']);

        $this->actingAs($verifier)
            ->patch(route('admissions.documents.review', $document), [
                'status' => 'requires_correction',
                'review_note' => 'Please upload a clearer copy.',
            ])
            ->assertRedirect();

        $document->refresh();

        $this->assertSame('requires_correction', $document->status);
        $this->assertSame('Please upload a clearer copy.', $document->review_note);
        $this->assertSame($verifier->id, $document->reviewed_by);
        $this->assertNotNull($document->reviewed_at);
    }
}
