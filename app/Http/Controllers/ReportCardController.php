<?php

namespace App\Http\Controllers;

use App\Actions\GenerateReportCards;
use App\Models\ReportCard;
use App\Models\Section;
use App\Models\Term;
use App\Services\AuditLogger;
use App\Services\DocumentCode;
use App\Services\Notifier;
use App\Services\SchoolSettings;
use App\Services\StudentAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReportCardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('reportcards.view'), 403);

        $filters = [
            'section' => $request->integer('section') ?: null,
            'term' => $request->integer('term') ?: null,
            'status' => $request->string('status')->trim()->toString(),
        ];

        $cards = ReportCard::query()
            ->with(['student:id,first_name,last_name,student_number', 'term:id,name', 'section.schoolClass'])
            ->when($filters['section'], fn ($query, $id) => $query->where('section_id', $id))
            ->when($filters['term'], fn ($query, $id) => $query->where('term_id', $id))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('reportcards.index', [
            'cards' => $cards,
            'filters' => $filters,
            'sections' => Section::with('schoolClass')->get()->sortBy('full_name'),
            'terms' => Term::orderBy('sequence')->get(),
            'canGenerate' => $request->user()->hasPermission('reportcards.generate'),
        ]);
    }

    /** Build (or rebuild) the cards for one section and term. */
    public function generate(Request $request, GenerateReportCards $action, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('reportcards.generate'), 403);

        $data = $request->validate([
            'section_id' => ['required', 'integer'],
            'term_id' => ['required', 'integer'],
        ]);

        // Both resolved through the tenant-scoped query.
        $section = Section::findOrFail($data['section_id']);
        $term = Term::findOrFail($data['term_id']);

        $count = $action->handle($section, $term);

        if ($count === 0) {
            return back()->withErrors([
                'section_id' => 'Nothing to build yet: that class has no approved marks for this term.',
            ]);
        }

        $audit->log('generated', 'Report cards',
            "{$count} report cards were generated for {$section->full_name} ({$term->name}).");

        return back()->with('status', "{$count} report cards generated as drafts. Review them, then publish.");
    }

    public function show(Request $request, ReportCard $reportCard): View
    {
        $this->authorizeCard($request, $reportCard);

        $reportCard->load([
            'student.guardians',
            'items.subject',
            'term',
            'academicYear',
            'section.schoolClass',
            'section.classTeacher',
        ]);

        return view('reportcards.show', [
            'card' => $reportCard,
            'school' => $request->user()->school,
            'verifyCode' => $this->verifyCode($reportCard),
        ]);
    }

    public function update(Request $request, ReportCard $reportCard, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('reportcards.generate'), 403);
        abort_unless($reportCard->school_id === $request->user()->school_id, 403);

        $data = $request->validate([
            'teacher_comment' => ['nullable', 'string', 'max:1000'],
            'principal_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $reportCard->update($data);

        $audit->log('updated', 'Report cards',
            "Comments were added to the report card for {$reportCard->student?->full_name}.", $reportCard);

        return back()->with('status', 'Comments saved.');
    }

    /**
     * Publishing is what makes a report card visible to a parent or student,
     * so it is a deliberate, separately audited step.
     */
    public function publish(Request $request, AuditLogger $audit, Notifier $notifier): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('reportcards.generate'), 403);

        $data = $request->validate([
            'section_id' => ['required', 'integer'],
            'term_id' => ['required', 'integer'],
        ]);

        $section = Section::findOrFail($data['section_id']);
        $term = Term::findOrFail($data['term_id']);

        $count = DB::transaction(fn () => ReportCard::where('section_id', $section->id)
            ->where('term_id', $term->id)
            ->where('status', 'draft')
            ->update(['status' => 'published', 'published_at' => now()]));

        if ($count === 0) {
            return back()->withErrors(['section_id' => 'There are no draft report cards to publish for that class and term.']);
        }

        $audit->log('published', 'Report cards',
            "{$count} report cards were published for {$section->full_name} ({$term->name}).");

        ReportCard::where('section_id', $section->id)
            ->where('term_id', $term->id)
            ->where('status', 'published')
            ->with('student.guardians')
            ->get()
            ->each(fn (ReportCard $card) => $notifier->reportCardPublished($card));

        return back()->with('status', "{$count} report cards published. Parents and students can now see them.");
    }

    /**
     * A parent may open their own child's published card, a student their own,
     * and staff any card they are permitted to view.
     */
    protected function authorizeCard(Request $request, ReportCard $reportCard): void
    {
        $user = $request->user();

        abort_unless(
            $reportCard->school_id === $user->school_id || $user->isSuperAdministrator(),
            404
        );

        if ($user->hasPermission('reportcards.view')) {
            return;
        }

        abort_unless($reportCard->status === 'published', 403, 'This report card has not been published yet.');

        $student = $reportCard->student;

        abort_unless($student !== null, 404);

        if ($student->user_id === $user->id) {
            return;
        }

        $guardian = $user->guardianProfile;

        abort_unless(
            $guardian !== null && $guardian->canViewAcademicsFor($student),
            403,
            'You are not able to view this report card.'
        );
    }

    /**
     * Download the report card as a file the family keeps (spec section 38).
     *
     * A self-contained HTML document rather than a PDF. No PDF library is
     * installed, and rather than pretend otherwise this produces something that
     * genuinely works: it opens in any browser with no internet connection, on
     * a phone as well as a laptop, and prints to paper or to PDF from there.
     * The stylesheet is inlined for that reason - a downloaded file that
     * silently loses its layout the moment it leaves the server is not a
     * document anyone can use.
     *
     * If a school later installs a PDF renderer this is the seam to change; the
     * route, the authorization and the filename stay as they are.
     */
    public function download(Request $request, ReportCard $reportCard, SchoolSettings $settings): Response
    {
        $this->authorizeCard($request, $reportCard);

        // A student may be barred from downloading even where they may view.
        if ($student = $request->user()->studentProfile) {
            abort_unless(
                app(StudentAccess::class)->allows($student, 'download_report_card'),
                403,
                'Downloading report cards is not switched on for your account.'
            );
        }

        $reportCard->load([
            'student.guardians', 'items.subject', 'term', 'academicYear',
            'section.schoolClass', 'section.classTeacher',
        ]);

        $html = view('reportcards.download', [
            'card' => $reportCard,
            'school' => $request->user()->school,
            'settings' => $settings->all(),
            'verifyCode' => $this->verifyCode($reportCard),
        ])->render();

        $name = Str::slug(
            ($reportCard->student?->full_name ?? 'report-card')
            .'-'.($reportCard->term?->name ?? 'full-year')
            .'-'.($reportCard->academicYear?->name ?? '')
        );

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'.html"',
        ]);
    }

    /**
     * The QR code for this card, or null while it is still a draft.
     *
     * A draft is not a document yet - it can still change, and it is not
     * visible to the family - so printing a verification code on one would be
     * promising that a scanner can confirm something the school has not
     * actually issued. The public check only recognises published cards.
     */
    protected function verifyCode(ReportCard $card): ?string
    {
        if ($card->status !== 'published') {
            return null;
        }

        return app(DocumentCode::class)->forDocument('report-card', (string) $card->getKey());
    }
}
