<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Document management (spec section 42).
 *
 * Files are written to the private disk and only ever leave through the
 * authorized download route below; nothing is linked to directly.
 */
class DocumentController extends Controller
{
    /** The record types a document may be filed against. */
    protected const SUBJECTS = [
        'student' => Student::class,
        'teacher' => Teacher::class,
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Document::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => $request->string('status')->trim()->toString(),
            'type' => $request->integer('type') ?: null,
            'expiring' => $request->boolean('expiring'),
        ];

        $documents = Document::query()
            ->with(['type', 'documentable', 'uploader:id,name', 'verifier:id,name'])
            ->search($filters['search'])
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'], fn ($query, $id) => $query->where('document_type_id', $id))
            ->when($filters['expiring'], fn ($query) => $query->expiring())
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'filters' => $filters,
            'types' => DocumentType::orderBy('applies_to')->orderBy('name')->get(),
            'statuses' => Document::STATUSES,
            'pendingCount' => Document::where('status', 'pending')->count(),
            'expiringCount' => Document::expiring()->where('status', 'verified')->count(),
            'subjects' => DocumentType::SUBJECTS,
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('create', Document::class);

        $data = $request->validate([
            'subject' => ['required', Rule::in(array_keys(self::SUBJECTS))],
            'subject_id' => ['required', 'integer'],
            'document_type_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:180'],
            'issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_on' => ['nullable', 'date', 'after:issued_on'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx', 'max:8192'],
        ], [
            'expires_on.after' => 'The expiry date must be after the date it was issued.',
        ]);

        // The owning record is resolved through the tenant-scoped query, so a
        // document cannot be filed against another school's student.
        $owner = $this->resolveOwner($data['subject'], $data['subject_id']);

        $type = isset($data['document_type_id'])
            ? DocumentType::findOrFail($data['document_type_id'])
            : null;

        $file = $request->file('file');

        $document = Document::create([
            'documentable_type' => $owner::class,
            'documentable_id' => $owner->id,
            'document_type_id' => $type?->id,
            'title' => $data['title'],
            // Private disk. Never served except through the download route.
            'path' => $file->store("documents/{$owner->school_id}/{$data['subject']}/{$owner->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'status' => 'pending',
            'issued_on' => $data['issued_on'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        $audit->log('uploaded', 'Documents',
            "Document \"{$document->title}\" was filed against ".$this->describe($owner).'.',
            $document);

        return back()->with('status', 'Document uploaded and awaiting verification.');
    }

    public function verify(Request $request, Document $document, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('verify', $document);

        $data = $request->validate([
            'status' => ['required', Rule::in(Document::STATUSES)],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $previous = $document->status;

        $document->update($data + [
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        $audit->log('verified', 'Documents',
            "Document \"{$document->title}\" was marked {$document->status}.",
            $document, ['status' => $previous], ['status' => $document->status]);

        return back()->with('status', 'Document review saved.');
    }

    public function download(Document $document): StreamedResponse
    {
        $this->authorize('download', $document);

        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroy(Document $document, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('delete', $document);

        $title = $document->title;

        // Soft-deleted: the file stays on disk so a mistaken deletion is
        // recoverable, in keeping with rule 10 about historical records.
        $document->delete();

        $audit->log('deleted', 'Documents', "Document \"{$title}\" was removed from the file.");

        return back()->with('status', 'Document removed.');
    }

    /* -------------------------------------------------------------- types */

    public function storeType(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'applies_to' => ['required', Rule::in(array_keys(DocumentType::SUBJECTS))],
            'description' => ['nullable', 'string', 'max:500'],
            'is_required' => ['boolean'],
            'expires' => ['boolean'],
        ]);

        $type = DocumentType::create($data + [
            'is_required' => $request->boolean('is_required'),
            'expires' => $request->boolean('expires'),
        ]);

        $audit->log('created', 'Documents', "Document type \"{$type->name}\" was added.", $type);

        return back()->with('status', 'Document type added.');
    }

    public function destroyType(Request $request, DocumentType $documentType, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);
        abort_unless($documentType->school_id === $request->user()->school_id, 403);

        if ($documentType->documents()->exists()) {
            return back()->withErrors([
                'document_type' => 'That type is still in use by filed documents, so it cannot be removed.',
            ]);
        }

        $name = $documentType->name;

        $documentType->delete();

        $audit->log('deleted', 'Documents', "Document type \"{$name}\" was removed.");

        return back()->with('status', 'Document type removed.');
    }

    /* ------------------------------------------------------------------ */

    protected function resolveOwner(string $subject, int $id): Student|Teacher
    {
        return match ($subject) {
            'student' => Student::findOrFail($id),
            'teacher' => Teacher::findOrFail($id),
        };
    }

    protected function describe(Student|Teacher $owner): string
    {
        return $owner->full_name;
    }
}
