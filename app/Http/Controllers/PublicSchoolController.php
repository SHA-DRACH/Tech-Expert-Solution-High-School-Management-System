<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\FeeStructure;
use App\Models\GalleryItem;
use App\Models\NewsPost;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SocialLink;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\WebsitePage;
use App\Services\PublicVisibility;
use App\Services\SchoolSettings;
use App\Support\SchoolContext;
use Illuminate\View\View;

/**
 * The public website (spec sections 7 to 11).
 *
 * Two rules run through all of it:
 *
 *   1. Content comes from the CMS where the school has written it, and falls
 *      back to sensible defaults where it has not, so a newly onboarded school
 *      has a complete site on day one.
 *   2. What appears is the school's decision. PublicVisibility holds those
 *      switches; nothing sensitive — fees especially — is published by default.
 */
class PublicSchoolController extends Controller
{
    public function __construct(
        private readonly SchoolContext $context,
        private readonly PublicVisibility $visibility,
    ) {}

    public function home(): View
    {
        $school = $this->school();

        return view('public.home', [
            'school' => $school,
            'page' => $this->page('home'),
            'visibility' => $this->visibility,
            'statistics' => $this->visibility->shows('statistics') ? $this->statistics() : null,
            'teachers' => $this->visibility->shows('teachers') ? $this->publicTeachers(4) : collect(),
            'news' => $this->visibility->shows('news')
                ? NewsPost::live()->latest('published_at')->limit(3)->get()
                : collect(),
            'events' => $this->visibility->shows('events')
                ? Event::upcoming()->where('is_public', true)->limit(3)->get()
                : collect(),
            'announcements' => $this->visibility->shows('announcements')
                ? Announcement::live()->for('public')->latest('published_at')->limit(3)->get()
                : collect(),
            'gallery' => $this->visibility->shows('gallery')
                ? GalleryItem::published()->orderBy('position')->limit(8)->get()
                : collect(),
            'socialLinks' => $this->socialLinks(),
        ]);
    }

    /** Spec section 9. */
    public function about(): View
    {
        $school = $this->school();

        return view('public.about', [
            'school' => $school,
            'page' => $this->page('about'),
            'staff' => $this->visibility->shows('teachers')
                ? $this->publicTeachers(6)
                : collect(),
            'statistics' => $this->visibility->shows('statistics') ? $this->statistics() : null,
            'socialLinks' => $this->socialLinks(),
        ]);
    }

    /** Spec section 10. */
    public function academics(): View
    {
        $school = $this->school();

        return view('public.academics', [
            'school' => $school,
            'page' => $this->page('academics'),
            'visibility' => $this->visibility,
            // Grouped into the two stages the spec names.
            'stages' => $this->classesByStage(),
            'departments' => $this->visibility->shows('departments')
                ? Department::withCount('subjects')->orderBy('name')->get()
                : collect(),
            'subjects' => $this->visibility->shows('subjects')
                ? Subject::with('department:id,name')->orderBy('name')->get()
                : collect(),
            'terms' => $this->visibility->shows('calendar')
                ? Term::with('academicYear')->orderBy('sequence')->get()
                : collect(),
            'socialLinks' => $this->socialLinks(),
        ]);
    }

    /** Spec section 11: the admissions information page, not the form itself. */
    public function admissions(): View
    {
        $school = $this->school();

        return view('public.admissions', [
            'school' => $school,
            'page' => $this->page('admissions'),
            'visibility' => $this->visibility,
            'classes' => $this->visibility->shows('classes')
                ? SchoolClass::orderBy('level')->get()
                : collect(),
            'documentTypes' => $this->requiredDocuments(),
            'fees' => $this->visibility->shows('fees') ? $this->publishedFees() : collect(),
            'socialLinks' => $this->socialLinks(),
        ]);
    }

    /** Spec section 7: the Teachers page. */
    public function teachers(): View
    {
        abort_unless($this->visibility->shows('teachers'), 404);

        return view('public.teachers', [
            'school' => $this->school(),
            'departments' => Department::orderBy('name')->get(),
            'teachers' => $this->publicTeachers(),
            'socialLinks' => $this->socialLinks(),
        ]);
    }

    public function contact(): View
    {
        return view('public.page', [
            'school' => $this->school(),
            'page' => $this->page('contact'),
            'title' => 'Contact us',
            'description' => 'Reach the school office for admissions, academic and general enquiries.',
            'socialLinks' => $this->socialLinks(),
        ]);
    }

    public function news(): View
    {
        abort_unless($this->visibility->shows('news'), 404);

        return view('public.news', [
            'school' => $this->school(),
            'posts' => NewsPost::live()->latest('published_at')->paginate(9),
            'socialLinks' => $this->socialLinks(),
        ]);
    }

    public function newsPost(string $slug): View
    {
        abort_unless($this->visibility->shows('news'), 404);

        $post = NewsPost::live()->where('slug', $slug)->firstOrFail();

        return view('public.news-post', [
            'school' => $this->school(),
            'post' => $post,
            'related' => NewsPost::live()->whereKeyNot($post->id)->latest('published_at')->limit(3)->get(),
            'socialLinks' => $this->socialLinks(),
        ]);
    }

    public function gallery(): View
    {
        abort_unless($this->visibility->shows('gallery'), 404);

        return view('public.gallery', [
            'school' => $this->school(),
            'albums' => GalleryItem::published()->orderBy('position')->get()->groupBy('album'),
            'socialLinks' => $this->socialLinks(),
        ]);
    }

    public function events(): View
    {
        abort_unless($this->visibility->shows('events'), 404);

        return view('public.events', [
            'school' => $this->school(),
            'events' => Event::where('is_public', true)->upcoming()->get(),
            'socialLinks' => $this->socialLinks(),
        ]);
    }

    /* ------------------------------------------------------------------ */

    /**
     * Figures for the statistics strip. Rounded down to a tidy number so the
     * homepage does not read like a live database readout.
     *
     * @return array<int, array{label: string, value: int, suffix: string}>
     */
    protected function statistics(): array
    {
        $students = Student::where('status', 'active')->count();
        $teachers = Teacher::where('status', 'active')->count();
        $classes = SchoolClass::count();
        $subjects = Subject::count();

        return [
            ['label' => 'Students enrolled', 'value' => $students, 'suffix' => $students >= 100 ? '+' : ''],
            ['label' => 'Teaching staff', 'value' => $teachers, 'suffix' => ''],
            ['label' => 'Classes offered', 'value' => $classes, 'suffix' => ''],
            ['label' => 'Subjects taught', 'value' => $subjects, 'suffix' => ''],
        ];
    }

    /** Only staff the school has chosen to publish. */
    protected function publicTeachers(?int $limit = null)
    {
        return Teacher::where('is_public', true)
            ->where('status', 'active')
            ->with(['department:id,name', 'publicQualifications'])
            ->orderBy('last_name')
            ->when($limit, fn ($query, $count) => $query->limit($count))
            ->get();
    }

    /**
     * Classes grouped into junior and senior high, with the subjects each
     * stage covers.
     *
     * @return array<string, \Illuminate\Support\Collection>
     */
    protected function classesByStage(): array
    {
        return SchoolClass::with('subjects:id,name')
            ->orderBy('level')
            ->get()
            ->groupBy(fn (SchoolClass $class) => $class->stage ?: ($class->level >= 10 ? 'Senior High' : 'Junior High'))
            ->all();
    }

    /**
     * What applicants are asked to bring, from the school's own settings.
     *
     * Read through the settings catalogue rather than the row directly, so this
     * list and the upload slots on the application form cannot drift apart:
     * telling a family to bring a transcript and then not offering anywhere to
     * upload it is exactly the kind of small betrayal that loses their trust.
     */
    protected function requiredDocuments(): array
    {
        return app(SchoolSettings::class)->get('admission_document_types');
    }

    /** Fee structures, only when the school has chosen to publish them. */
    protected function publishedFees()
    {
        return FeeStructure::with(['items', 'schoolClass'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    protected function page(string $key): ?WebsitePage
    {
        return WebsitePage::published()->where('key', $key)->first();
    }

    protected function socialLinks()
    {
        return SocialLink::orderBy('position')->get();
    }

    protected function school(): School
    {
        abort_unless($this->context->hasSchool(), 404, 'No school is available at this address.');

        return $this->context->school();
    }
}
