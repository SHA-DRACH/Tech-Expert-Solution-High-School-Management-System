@props(['title' => null, 'heading' => null])

@php
    use App\Support\SchoolContext;

    $user = auth()->user();
    $context = app(SchoolContext::class);
    $school = $context->school();

    /*
     | Nav entries declare the permission that reveals them, so the sidebar
     | shows exactly what this account is allowed to open. An entry may also
     | carry a `when` flag for things that depend on who someone *is* rather
     | than what they may do.
     */
    $teacher = $user?->teacherProfile;
    $isTeacher = $teacher !== null;

    $studentProfile = $user?->studentProfile;
    $guardianProfile = $user?->guardianProfile;

    $navigation = [];

    /*
     | A student's own workspace.
     |
     | Students and guardians hold no permission slugs at all, so every entry
     | here has `can => null` - "no permission needed" - and is revealed by
     | `when` instead. What a student may open is decided by StudentAccess, the
     | same service the controllers check, so the menu never offers a page that
     | would answer 403.
     */
    if ($studentProfile) {
        $access = app(App\Services\StudentAccess::class)->for($studentProfile);

        // Spec section 23's module list, each shown only where the school has
        // switched that ability on.
        $navigation['My learning'] = [
            ['label' => 'Overview', 'icon' => '▦', 'route' => 'student.dashboard', 'active' => 'student.dashboard', 'can' => null],
            ['label' => 'My class', 'icon' => '⌘', 'route' => 'student.class', 'active' => 'student.class', 'can' => null, 'when' => $access->get('view_subjects', false)],
            ['label' => 'My subjects', 'icon' => '◈', 'route' => 'student.subjects', 'active' => 'student.subjects', 'can' => null, 'when' => $access->get('view_subjects', false)],
            ['label' => 'My grades', 'icon' => '▦', 'route' => 'student.grades', 'active' => 'student.grades', 'can' => null, 'when' => $access->get('view_grades', false)],
            ['label' => 'Exams', 'icon' => '⌸', 'route' => 'student.exams', 'active' => 'student.exams', 'can' => null, 'when' => $access->get('view_grades', false)],
            ['label' => 'Assignments', 'icon' => '🗎', 'route' => 'student.assignments', 'active' => 'student.assignments', 'can' => null, 'when' => $access->get('view_assignments', false)],
            ['label' => 'Report cards', 'icon' => '🗎', 'route' => 'student.reportcards', 'active' => 'student.reportcards', 'can' => null, 'when' => $access->get('view_report_cards', false)],
            ['label' => 'Grade sheet', 'icon' => '🗎', 'route' => 'student.gradesheet', 'active' => 'student.gradesheet', 'can' => null, 'when' => $access->get('view_report_cards', false)],
            ['label' => 'Progress report', 'icon' => '🗎', 'route' => 'student.progress', 'active' => 'student.progress', 'can' => null, 'when' => $access->get('view_report_cards', false)],
            ['label' => 'Attendance', 'icon' => '◷', 'route' => 'student.attendance', 'active' => 'student.attendance', 'can' => null, 'when' => $access->get('view_attendance', false)],
            ['label' => 'Fees', 'icon' => '◎', 'route' => 'student.fees', 'active' => 'student.fees', 'can' => null, 'when' => $access->get('view_fees', false)],
            ['label' => 'Timetable', 'icon' => '▤', 'route' => 'student.timetable', 'active' => 'student.timetable', 'can' => null, 'when' => $access->get('view_timetable', false)],
            ['label' => 'Announcements', 'icon' => '✦', 'route' => 'student.announcements', 'active' => 'student.announcements', 'can' => null],
            ['label' => 'Messages', 'icon' => '✉', 'route' => 'messages.index', 'active' => 'messages.*', 'can' => null, 'when' => $access->get('send_messages', false)],
        ];
    }

    /*
     | A guardian's workspace. The child selector lives on each page, so these
     | are deliberately not per-child links.
     */
    if ($guardianProfile) {
        $navigation['My children'] = [
            ['label' => 'Overview', 'icon' => '▦', 'route' => 'parent.dashboard', 'active' => 'parent.dashboard', 'can' => null],
            ['label' => 'Class schedule', 'icon' => '▤', 'route' => 'parent.schedule', 'active' => 'parent.schedule', 'can' => null],
            ['label' => 'Results', 'icon' => '◈', 'route' => 'parent.grades', 'active' => 'parent.grades', 'can' => null],
            ['label' => 'Grade sheet', 'icon' => '🗎', 'route' => 'parent.gradesheet', 'active' => 'parent.gradesheet', 'can' => null],
            ['label' => 'Progress report', 'icon' => '🗎', 'route' => 'parent.progress', 'active' => 'parent.progress', 'can' => null],
            ['label' => 'Assignments', 'icon' => '🗎', 'route' => 'parent.assignments', 'active' => 'parent.assignments', 'can' => null],
            ['label' => 'Attendance', 'icon' => '◷', 'route' => 'parent.attendance', 'active' => 'parent.attendance', 'can' => null],
            ['label' => 'Fees', 'icon' => '◎', 'route' => 'parent.fees', 'active' => 'parent.fees', 'can' => null],
            ['label' => 'Teachers', 'icon' => '✎', 'route' => 'parent.teachers', 'active' => 'parent.teachers', 'can' => null],
            ['label' => 'Requests', 'icon' => '✆', 'route' => 'parent.requests', 'active' => 'parent.requests', 'can' => null],
            ['label' => 'Messages', 'icon' => '✉', 'route' => 'messages.index', 'active' => 'messages.*', 'can' => null],
        ];
    }

    /*
     | A teacher gets their own section, and it comes first.
     |
     | They used to have a five-link top bar and nothing else, which left them
     | holding grades.enter and attendance.record with no way to reach
     | Assessments, Attendance, Report cards or the mark upload at all. The
     | sidebar is already permission-filtered, so putting them in it shows each
     | teacher exactly the work they are allowed to do.
     */
    if ($isTeacher) {
        // Spec section 27. Each entry reads only through the teacher's own
        // assignments, so none of them widens what they can reach.
        $navigation['My teaching'] = [
            ['label' => 'Overview', 'icon' => '▦', 'route' => 'teaching.dashboard', 'active' => 'teaching.dashboard', 'can' => 'dashboard.view'],
            ['label' => 'My classes', 'icon' => '⌘', 'route' => 'teaching.classes', 'active' => 'teaching.classes', 'can' => 'dashboard.view'],
            ['label' => 'My subjects', 'icon' => '◈', 'route' => 'teaching.subjects', 'active' => 'teaching.subjects', 'can' => 'dashboard.view'],
            ['label' => 'My students', 'icon' => '◉', 'route' => 'teaching.students', 'active' => 'teaching.students', 'can' => 'dashboard.view'],
            ['label' => 'Grade sheet', 'icon' => '⌗', 'route' => 'gradesheet.index', 'active' => 'gradesheet.*', 'can' => 'grades.enter'],
            ['label' => 'Assignments', 'icon' => '🗎', 'route' => 'teaching.assignments', 'active' => 'teaching.assignments', 'can' => 'dashboard.view'],
            ['label' => 'My timetable', 'icon' => '▤', 'route' => 'teaching.timetable', 'active' => 'teaching.timetable', 'can' => 'dashboard.view'],
            ['label' => 'Announcements', 'icon' => '✦', 'route' => 'teaching.announcements', 'active' => 'teaching.announcements', 'can' => 'dashboard.view'],
        ];
    }

    $navigation += [
        'Workspace' => [
            // A teacher's overview is their own, above; this one is the
            // school-wide dashboard and would be the wrong "Overview" for them.
            ['label' => 'Overview', 'icon' => '▦', 'route' => 'dashboard', 'active' => 'dashboard', 'can' => 'dashboard.view', 'when' => ! $isTeacher],
            ['label' => 'Admissions', 'icon' => '✦', 'route' => 'admissions.index', 'active' => 'admissions.*', 'can' => 'admissions.view'],
            ['label' => 'Registrar', 'icon' => '◫', 'route' => 'registrar.index', 'active' => 'registrar.*', 'can' => 'students.view'],
            ['label' => 'Students', 'icon' => '◉', 'route' => 'students.index', 'active' => 'students.index', 'can' => 'students.view'],
            ['label' => 'Parents & guardians', 'icon' => '❋', 'route' => 'guardians.index', 'active' => 'guardians.*', 'can' => 'guardians.view'],
            ['label' => 'Teachers & staff', 'icon' => '✎', 'route' => 'teachers.index', 'active' => 'teachers.*', 'can' => 'teachers.view'],
            ['label' => 'Documents', 'icon' => '🗎', 'route' => 'documents.index', 'active' => 'documents.*', 'can' => 'students.view'],
        ],
        'Academics' => [
            ['label' => 'Academic structure', 'icon' => '⌘', 'route' => 'academics.index', 'active' => 'academics.index', 'can' => 'academics.view'],
            ['label' => 'Subjects', 'icon' => '◈', 'route' => 'subjects.index', 'active' => 'subjects.*', 'can' => 'academics.view'],
            ['label' => 'Teaching assignments', 'icon' => '⊞', 'route' => 'assignments.index', 'active' => 'assignments.*', 'can' => 'academics.view'],
            ['label' => 'Attendance', 'icon' => '◷', 'route' => 'attendance.index', 'active' => 'attendance.*', 'can' => 'attendance.view'],
            ['label' => 'Assessments', 'icon' => '◈', 'route' => 'assessments.index', 'active' => 'assessments.*', 'can' => 'grades.enter'],
            ['label' => 'Grade sheet', 'icon' => '⌗', 'route' => 'gradesheet.index', 'active' => 'gradesheet.*', 'can' => 'grades.approve'],
            ['label' => 'Mark sheet', 'icon' => '⌗', 'route' => 'marks.index', 'active' => 'marks.*', 'can' => 'grades.enter'],
            ['label' => 'Grade approvals', 'icon' => '✓', 'route' => 'grades.approvals', 'active' => 'grades.*', 'can' => 'grades.approve'],
            ['label' => 'Gradebook', 'icon' => '▦', 'route' => 'gradebook.index', 'active' => 'gradebook.*', 'can' => 'reportcards.view'],
            ['label' => 'Examinations', 'icon' => '⌸', 'route' => 'examinations.index', 'active' => 'examinations.*', 'can' => 'exams.view'],
            ['label' => 'Grade sheets & report cards', 'icon' => '🗎', 'route' => 'progress.index', 'active' => 'progress.*', 'can' => 'reportcards.view'],
            ['label' => 'Report cards', 'icon' => '🗎', 'route' => 'reportcards.index', 'active' => 'reportcards.*', 'can' => 'reportcards.view'],
            ['label' => 'Timetable', 'icon' => '▤', 'route' => 'timetable.index', 'active' => 'timetable.*', 'can' => 'timetable.view'],
        ],
        'Finance' => [
            ['label' => 'Fee structures', 'icon' => '⌗', 'route' => 'fees.index', 'active' => 'fees.*', 'can' => 'fees.manage'],
            ['label' => 'Invoices', 'icon' => '◫', 'route' => 'invoices.index', 'active' => 'invoices.*', 'can' => 'payments.view'],
            ['label' => 'Payments', 'icon' => '◎', 'route' => 'payments.index', 'active' => 'payments.*', 'can' => 'payments.view'],
            ['label' => 'Expenses', 'icon' => '◱', 'route' => 'expenses.index', 'active' => 'expenses.*', 'can' => 'expenses.manage'],
            ['label' => 'Scholarships', 'icon' => '◇', 'route' => 'scholarships.index', 'active' => 'scholarships.*', 'can' => 'scholarships.view'],
        ],
        'Communication' => [
            ['label' => 'Announcements', 'icon' => '✦', 'route' => 'announcements.index', 'active' => 'announcements.*', 'can' => 'announcements.manage'],
            ['label' => 'Events', 'icon' => '◈', 'route' => 'events.index', 'active' => 'events.*', 'can' => 'events.manage'],
            ['label' => 'Messages', 'icon' => '✉', 'route' => 'messages.index', 'active' => 'messages.*', 'can' => 'messages.send'],
            ['label' => 'Parent requests', 'icon' => '✉', 'route' => 'requests.index', 'active' => 'requests.*', 'can' => 'requests.manage'],
            ['label' => 'Gallery', 'icon' => '▨', 'route' => 'gallery.index', 'active' => 'gallery.*', 'can' => 'website.manage'],
            ['label' => 'Website', 'icon' => '▤', 'route' => 'website.index', 'active' => 'website.*', 'can' => 'website.manage'],
        ],
        'Administration' => [
            ['label' => 'Reports', 'icon' => '◱', 'route' => 'reports.index', 'active' => 'reports.*', 'can' => 'reports.view'],
            ['label' => 'Users & staff', 'icon' => '◌', 'route' => 'users.index', 'active' => 'users.*', 'can' => 'users.view'],
            ['label' => 'Roles & permissions', 'icon' => '⌸', 'route' => 'roles.index', 'active' => 'roles.*', 'can' => 'roles.view'],
            ['label' => 'Student portal access', 'icon' => '◐', 'route' => 'students.permissions', 'active' => 'students.permissions*', 'can' => 'students.update'],
            ['label' => 'Academic years & periods', 'icon' => '▤', 'route' => 'settings.years.index', 'active' => 'settings.years.*', 'can' => 'academics.manage'],
            ['label' => 'Periods & semesters', 'icon' => '◫', 'route' => 'periods.index', 'active' => 'periods.*', 'can' => 'academics.view'],
            ['label' => 'Settings', 'icon' => '⚙', 'route' => 'settings.index', 'active' => ['settings.index', 'settings.school.*', 'settings.group.*'], 'can' => 'settings.manage'],
            ['label' => 'Audit trail', 'icon' => '☰', 'route' => 'audit.index', 'active' => 'audit.*', 'can' => 'audit.view'],
        ],

        /*
         | Reachable by every signed-in account, whatever it may otherwise do,
         | which is why both entries are gated on dashboard.view rather than on
         | anything narrower. Notifications and "my account" were only in the
         | avatar menu, where people do not look for them.
         */
        'My account' => [
            // `can => null`: a parent or student holds no permission slugs and
            // must still reach their own notifications and profile.
            ['label' => 'Notifications', 'icon' => '✉', 'route' => 'notifications.index', 'active' => 'notifications.*', 'can' => null],
            ['label' => 'My profile', 'icon' => '◐', 'route' => 'profile.edit', 'active' => 'profile.*', 'can' => null],
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $school?->name ?? config('app.name') }}</title>

    @if ($school?->favicon_path)
        <link rel="icon" href="{{ Storage::disk('public')->url($school->favicon_path) }}">
    @endif

    {{-- Per-school branding, applied without a rebuild. --}}
    <style>
        :root {
            --brand-primary: {{ $school?->primary_color ?? '#1d4ed8' }};
            --brand-secondary: {{ $school?->secondary_color ?? '#0f766e' }};
        }
    </style>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>
<body class="bg-slate-50 font-sans text-slate-900 antialiased">
@include('partials.splash')
<div x-data="{ mobileNav: false }" class="min-h-screen lg:flex">

    {{-- Mobile navigation drawer --}}
    <div x-show="mobileNav" x-cloak class="fixed inset-0 z-40 lg:hidden">
        <div x-show="mobileNav"
             x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="mobileNav = false"></div>
        <aside
            x-show="mobileNav"
            x-transition:enter="transition duration-350 ease-[cubic-bezier(0.22,1,0.36,1)]"
            x-transition:enter-start="-translate-x-full opacity-0"
            x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition duration-250 ease-in"
            x-transition:leave-start="translate-x-0 opacity-100"
            x-transition:leave-end="-translate-x-full opacity-0"
            class="relative flex h-full w-72 flex-col bg-slate-950 px-5 py-6 text-slate-300 shadow-2xl"
        >
            @include('partials.sidebar', ['navigation' => $navigation, 'school' => $school])
        </aside>
    </div>

    {{-- Desktop sidebar: pinned to the viewport, scrolling on its own only when
         the navigation is taller than the screen. Only the main column moves. --}}
    <aside class="hidden w-72 shrink-0 flex-col bg-slate-950 px-5 py-6 text-slate-300
                  lg:sticky lg:top-0 lg:flex lg:h-screen lg:overflow-y-auto">
        @include('partials.sidebar', ['navigation' => $navigation, 'school' => $school])
    </aside>

    <main class="min-w-0 flex-1">
        <header class="site-header sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <button
                    type="button"
                    @click="mobileNav = true"
                    class="grid size-9 shrink-0 place-items-center rounded-lg border border-slate-200 text-slate-600 lg:hidden"
                    aria-label="Open navigation"
                >☰</button>

                <div class="min-w-0">
                    <p class="truncate text-xs font-medium text-slate-500">
                        {{ $school?->name ?? 'GSMS Platform' }}
                    </p>
                    <h1 class="truncate text-sm font-semibold">{{ $heading ?? $title ?? 'Dashboard' }}</h1>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <x-ui.notification-bell />

                @if ($user->isSuperAdministrator() && $school)
                    <form method="POST" action="{{ route('platform.schools.clear') }}" class="hidden sm:block">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" size="sm">Leave school</x-ui.button>
                    </form>
                @endif

                <div x-data="{ open: false }" class="relative">
                    <button
                        type="button"
                        @click="open = ! open"
                        @click.outside="open = false"
                        class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100"
                        aria-haspopup="menu"
                        :aria-expanded="open"
                    >
                        <span class="grid size-8 place-items-center rounded-full bg-brand text-xs font-semibold text-white">
                            {{ Str::of($user->name)->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('') }}
                        </span>
                        <span class="hidden text-left sm:block">
                            <span class="block text-sm font-medium leading-tight">{{ $user->name }}</span>
                            <span class="block text-xs text-slate-500">{{ $user->roles->first()?->name ?? 'User' }}</span>
                        </span>
                    </button>

                    <div
                        x-show="open"
                        x-cloak
                        x-transition:enter="transition duration-200 ease-[cubic-bezier(0.22,1,0.36,1)]"
                        x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                        x-transition:leave="transition duration-150 ease-in"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute right-0 z-40 mt-2 w-56 origin-top-right rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl"
                        role="menu"
                    >
                        <div class="px-3 py-2">
                            <p class="truncate text-sm font-medium">{{ $user->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                        </div>

                        <div class="my-1 border-t border-slate-100"></div>

                        {{-- Every account may edit its own profile, whatever it can otherwise reach. --}}
                        <a href="{{ route('profile.edit') }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">
                            My account
                        </a>

                        @can('viewAny', App\Models\School::class)
                            <a href="{{ route('platform.schools') }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">
                                Platform schools
                            </a>
                        @endcan

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50" role="menuitem">
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <div class="enter-rise mx-auto max-w-7xl px-4 py-8 sm:px-8">
            {{ $slot }}
        </div>
    </main>
</div>

<x-ui.toast />

{{-- Available on every signed-in screen, so a question can be asked
     wherever it occurs to someone rather than only on a dedicated page. --}}
<x-ui.assistant />

@livewireScriptConfig
</body>
</html>
