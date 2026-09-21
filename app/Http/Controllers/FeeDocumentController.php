<?php

namespace App\Http\Controllers;

use App\Models\FeeStructure;
use App\Services\StudentAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The PDF attached to a fee structure, handed to whoever may have it.
 *
 * Two doors:
 *
 *   - public: only a PDF the school chose to publish on the Online services
 *     page, for an active structure. Anyone may download it.
 *   - signed in: finance staff; a student allowed to see fees, for their own
 *     class or the whole school; a parent cleared to see a child's fees, for
 *     that child's class or the whole school.
 *
 * The file itself lives on the private disk, so these are the only ways out.
 */
class FeeDocumentController extends Controller
{
    public function public(FeeStructure $feeStructure): StreamedResponse
    {
        abort_unless(
            $feeStructure->is_active && $feeStructure->document_on_website && $feeStructure->hasDocument(),
            404,
        );

        return $this->send($feeStructure);
    }

    public function portal(Request $request, FeeStructure $feeStructure): StreamedResponse
    {
        abort_unless($feeStructure->hasDocument(), 404);

        $user = $request->user();

        $allowed = $user->hasAnyPermission(['fees.manage', 'payments.view'])
            || $this->studentMay($request, $feeStructure)
            || $this->guardianMay($request, $feeStructure);

        abort_unless($allowed, 403);

        // Inactive structures are staff-only: families only see what is current.
        abort_unless($feeStructure->is_active || $user->hasAnyPermission(['fees.manage', 'payments.view']), 404);

        return $this->send($feeStructure);
    }

    protected function studentMay(Request $request, FeeStructure $structure): bool
    {
        $student = $request->user()->studentProfile()->with('currentEnrollment.section')->first();

        return $student !== null
            && app(StudentAccess::class)->allows($student, 'view_fees')
            && FeeStructure::documentsFor($student)->contains('id', $structure->id);
    }

    protected function guardianMay(Request $request, FeeStructure $structure): bool
    {
        $guardian = $request->user()->guardianProfile()->first();

        if ($guardian === null) {
            return false;
        }

        return $guardian->students()->with('currentEnrollment.section')->get()
            ->contains(fn ($child) => $guardian->canViewFinanceFor($child)
                && FeeStructure::documentsFor($child)->contains('id', $structure->id));
    }

    protected function send(FeeStructure $structure): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($structure->document_path), 404);

        $name = $structure->document_name ?: Str::slug($structure->name).'.pdf';

        return Storage::disk('local')->download($structure->document_path, $name, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
