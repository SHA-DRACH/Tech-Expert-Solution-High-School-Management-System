@php
    $student = session('studentResult');
    $application = session('applicationResult');
    $by = old('by', 'id');

    $field = 'block w-full rounded-lg border border-white/25 bg-white/10 px-4 py-3 text-sm text-white placeholder-white/55 '
        .'outline-none transition focus:border-white/70 focus:bg-white/15';
    $button = 'press inline-flex items-center justify-center rounded-lg border border-white/25 bg-white/20 px-5 py-3 '
        .'text-sm font-semibold text-white transition hover:bg-white/30';
    $card = 'rounded-2xl border border-white/10 bg-white/10 p-6 shadow-lg backdrop-blur';

    // All wording is the school's to change (Website › Online services).
    $t = App\Support\SiteContent::for($page, 'online-services', $school);
@endphp

<x-layouts.public :school="$school" :title="$t->title()" :social-links="$socialLinks">
    {{-- Hero --}}
    <section class="section--ink relative overflow-hidden py-14 sm:py-18">
        <div class="relative mx-auto max-w-7xl px-4 sm:px-6">
            <nav aria-label="Breadcrumb" data-aos="fade-up">
                <ol class="flex items-center gap-2 text-xs text-white/60">
                    <li><a href="{{ route('home') }}" class="link-underline hover:text-white">Home</a></li>
                    <li aria-hidden="true">/</li>
                    <li class="font-medium text-white/90" aria-current="page">{{ $t->title() }}</li>
                </ol>
            </nav>

            <p class="eyebrow eyebrow--onDark mt-6" data-aos="fade-up" data-aos-delay="60">{{ $t('eyebrow') }}</p>
            <h1 class="mt-4 font-display text-3xl font-bold text-white sm:text-4xl lg:text-5xl" data-aos="fade-up" data-aos-delay="120">
                {{ $t->title() }}
            </h1>
            <p class="mt-4 max-w-2xl text-white/75" data-aos="fade-up" data-aos-delay="180">
                {{ $t->summary() }}
            </p>

            <div class="mt-6 flex flex-wrap gap-2" data-aos="fade-up" data-aos-delay="240">
                @foreach ([
                    '#application-status' => 'Application status',
                    '#verify-student' => 'Verify a student',
                    '#verify-document' => 'Verify a document',
                    '#portal' => 'Parent & student portal',
                    '#fees' => 'Paying fees',
                    '#contact' => 'Contact',
                ] as $anchor => $label)
                    <a href="{{ $anchor }}" class="rounded-full border border-white/20 px-3 py-1.5 text-xs text-white/85 transition hover:border-white/50 hover:text-white">{{ $label }}</a>
                @endforeach
            </div>

            {{-- The three checks --}}
            <div class="mt-10 grid gap-6 lg:grid-cols-3">
                {{-- Application status --}}
                <div id="application-status" class="{{ $card }} scroll-mt-28" data-aos="fade-up">
                    <h2 class="flex items-center gap-2 font-display text-lg font-bold text-white">
                        <span aria-hidden="true">🔍</span> {{ $t('application_title') }}
                    </h2>
                    <p class="mt-2 text-sm text-white/75">{{ $t('application_intro') }}</p>

                    <form method="POST" action="{{ route('online.application') }}" class="mt-5 space-y-3">
                        @csrf
                        <label class="sr-only" for="application_number">Application number</label>
                        <input id="application_number" name="application_number" value="{{ old('application_number') }}" required
                               placeholder="Enter application no." class="{{ $field }}">
                        <label class="sr-only" for="phone">Guardian phone number</label>
                        <input id="phone" name="phone" type="tel" required autocomplete="tel"
                               placeholder="Guardian phone used on the form" class="{{ $field }}">
                        @error('application_number')<p class="text-xs text-rose-200">{{ $message }}</p>@enderror
                        @error('phone')<p class="text-xs text-rose-200">{{ $message }}</p>@enderror
                        <button type="submit" class="{{ $button }} w-full">Check</button>
                    </form>

                    @if ($application)
                        <div role="status" class="mt-5 rounded-xl bg-white p-4 text-sm text-slate-700">
                            @if ($application['found'])
                                <p @class([
                                    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $application['tone'] === 'good',
                                    'bg-rose-100 text-rose-800' => $application['tone'] === 'bad',
                                    'bg-amber-100 text-amber-800' => $application['tone'] === 'action',
                                    'bg-sky-100 text-sky-800' => $application['tone'] === 'waiting',
                                ])>{{ $application['status'] }}</p>
                                <p class="mt-2 font-medium text-slate-900">{{ $application['name'] }} · {{ $application['number'] }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $application['class'] ? 'Applying for '.$application['class'].' · ' : '' }}submitted {{ $application['submitted'] }}
                                </p>
                                <p class="mt-2">{{ $application['next'] }}</p>
                            @else
                                <p class="font-medium text-slate-900">No application matches those details.</p>
                                <p class="mt-1 text-xs text-slate-500">Check the application number and use the guardian phone number written on the form.</p>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Verify a student --}}
                <div id="verify-student" class="{{ $card }} scroll-mt-28" data-aos="fade-up" data-aos-delay="80"
                     x-data="{ by: '{{ $lookupByName ? $by : 'id' }}' }">
                    <h2 class="flex items-center gap-2 font-display text-lg font-bold text-white">
                        <span aria-hidden="true">🎓</span> {{ $t('student_title') }}
                    </h2>
                    <p class="mt-2 text-sm text-white/75">
                        {{ $t('student_intro') }}
                    </p>

                    <form method="POST" action="{{ route('online.student') }}" class="mt-5 space-y-3">
                        @csrf

                        @if ($lookupByName)
                            <div class="grid grid-cols-2 gap-1 rounded-lg bg-white/10 p-1 text-xs font-semibold" role="radiogroup" aria-label="Check by">
                                <label class="cursor-pointer rounded-md px-3 py-2 text-center transition" :class="by === 'id' ? 'bg-white text-slate-900' : 'text-white/80'">
                                    <input type="radio" name="by" value="id" x-model="by" class="sr-only"> Student ID
                                </label>
                                <label class="cursor-pointer rounded-md px-3 py-2 text-center transition" :class="by === 'name' ? 'bg-white text-slate-900' : 'text-white/80'">
                                    <input type="radio" name="by" value="name" x-model="by" class="sr-only"> Name &amp; class
                                </label>
                            </div>
                        @else
                            <input type="hidden" name="by" value="id">
                        @endif

                        <div x-show="by === 'id'">
                            <label class="sr-only" for="student_number">Student ID</label>
                            <input id="student_number" name="student_number" value="{{ old('student_number') }}"
                                   placeholder="Enter student ID, e.g. GFI-2026-00001" class="{{ $field }}" :required="by === 'id'">
                        </div>

                        @if ($lookupByName)
                            <div x-show="by === 'name'" x-cloak class="space-y-3">
                                <label class="sr-only" for="full_name">Full name</label>
                                <input id="full_name" name="full_name" value="{{ old('full_name') }}"
                                       placeholder="Student’s full name" class="{{ $field }}" :required="by === 'name'">
                                <label class="sr-only" for="section_id">Class</label>
                                <select id="section_id" name="section_id" class="{{ $field }}" :required="by === 'name'">
                                    <option value="" class="text-slate-900">Select class</option>
                                    @foreach ($sections as $section)
                                        <option value="{{ $section->id }}" class="text-slate-900" @selected(old('section_id') == $section->id)>{{ $section->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @error('student_number')<p class="text-xs text-rose-200">{{ $message }}</p>@enderror
                        @error('full_name')<p class="text-xs text-rose-200">{{ $message }}</p>@enderror
                        @error('section_id')<p class="text-xs text-rose-200">{{ $message }}</p>@enderror
                        <button type="submit" class="{{ $button }} w-full">Verify</button>
                    </form>

                    @if ($student)
                        <div role="status" class="mt-5 rounded-xl bg-white p-4 text-sm text-slate-700">
                            @if ($student['found'])
                                <p @class([
                                    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $student['current'],
                                    'bg-slate-100 text-slate-700' => ! $student['current'],
                                ])>{{ $student['current'] ? '✓ Verified student' : 'Former or inactive student' }}</p>
                                <p class="mt-2 font-medium text-slate-900">{{ $student['name'] }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $student['status'] }}{{ $student['class'] ? ' · '.$student['class'] : '' }}{{ $student['year'] ? ' · '.$student['year'] : '' }}
                                </p>
                            @else
                                <p class="font-medium text-slate-900">No student matches those details.</p>
                                <p class="mt-1 text-xs text-slate-500">Check the spelling and class, or use the student ID printed on the student’s papers.</p>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Verify a document --}}
                <div id="verify-document" class="{{ $card }} scroll-mt-28" data-aos="fade-up" data-aos-delay="160">
                    <h2 class="flex items-center gap-2 font-display text-lg font-bold text-white">
                        <span aria-hidden="true">📄</span> {{ $t('document_title') }}
                    </h2>
                    <p class="mt-2 text-sm text-white/75">
                        {{ $t('document_intro') }}
                    </p>

                    <form method="POST" action="{{ route('online.document') }}" class="mt-5 space-y-3">
                        @csrf
                        <label class="sr-only" for="code">Verification code</label>
                        <input id="code" name="code" value="{{ old('code') }}" required autocomplete="off"
                               placeholder="e.g. GS-7K3P-X9QA" class="{{ $field }} font-mono uppercase tracking-wider">
                        @error('code')<p class="text-xs text-rose-200">{{ $message }}</p>@enderror
                        <button type="submit" class="{{ $button }} w-full">Check document</button>
                    </form>

                    <p class="mt-4 text-xs text-white/60">
                        Codes starting <strong class="text-white/80">GS</strong> are grade sheets; <strong class="text-white/80">RC</strong> are report cards.
                        You can also scan the QR code on the document with a phone.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- Information --}}
    <section class="section">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 md:grid-cols-2 lg:grid-cols-3">
            {{-- Admission --}}
            <article class="rounded-2xl border border-slate-200 bg-white p-6" data-aos="fade-up">
                <p class="eyebrow">Admission</p>
                <h2 class="mt-2 font-display text-xl font-bold text-slate-900">{{ $t('apply_title') }}</h2>
                @if ($admissionsOpen)
                    <p class="mt-3 text-sm text-slate-600">{{ $t('apply_body') }}</p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <x-ui.button :href="route('apply')">Apply for admission</x-ui.button>
                        <x-ui.button :href="route('public.admissions')" variant="secondary">Requirements</x-ui.button>
                    </div>
                @else
                    <p class="mt-3 text-sm text-slate-600">Applications are closed at the moment. You can still check an application you have already made above.</p>
                    <div class="mt-5"><x-ui.button :href="route('public.admissions')" variant="secondary">About admissions</x-ui.button></div>
                @endif
            </article>

            {{-- Portal --}}
            <article id="portal" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6" data-aos="fade-up" data-aos-delay="80">
                <p class="eyebrow">Parents &amp; students</p>
                <h2 class="mt-2 font-display text-xl font-bold text-slate-900">{{ $t('portal_title') }}</h2>
                <p class="mt-3 text-sm text-slate-600">Sign in with the account the school gave you to:</p>
                <ul class="mt-3 space-y-1.5 text-sm text-slate-700">
                    @foreach ($t->lines('portal_items') as $item)
                        <li class="flex gap-2"><span class="text-brand" aria-hidden="true">✓</span>{{ $item }}</li>
                    @endforeach
                </ul>
                <div class="mt-5"><x-ui.button :href="route('login')">Sign in</x-ui.button></div>
                <p class="mt-3 text-xs text-slate-500">{{ $t('portal_note') }}</p>
            </article>

            {{-- Fees --}}
            <article id="fees" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6" data-aos="fade-up" data-aos-delay="160">
                <p class="eyebrow">Finance</p>
                <h2 class="mt-2 font-display text-xl font-bold text-slate-900">{{ $t('fees_title') }}</h2>
                @if (filled($paymentInstructions))
                    <p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $paymentInstructions }}</p>
                @endif
                <p class="mt-3 text-sm text-slate-600">Every payment gets a receipt with a QR code you can scan to confirm it with the school.</p>

                @include('partials.fee-documents', ['documents' => $feeDocuments, 'public' => true, 'heading' => $t('fees_documents')])
                @if ($showFees)
                    <div class="mt-5"><x-ui.button :href="route('public.admissions')" variant="secondary">See the fee schedule</x-ui.button></div>
                @endif
            </article>

            {{-- About verification --}}
            <article class="rounded-2xl border border-slate-200 bg-white p-6 md:col-span-2" data-aos="fade-up">
                <p class="eyebrow">For employers and other schools</p>
                <h2 class="mt-2 font-display text-xl font-bold text-slate-900">{{ $t('verify_title') }}</h2>
                <div class="mt-3 space-y-3 text-sm text-slate-600">
                    @foreach ($t->paragraphs('verify_body') as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            </article>

            {{-- Contact --}}
            <article id="contact" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6" data-aos="fade-up" data-aos-delay="80">
                <p class="eyebrow">Help</p>
                <h2 class="mt-2 font-display text-xl font-bold text-slate-900">{{ $t('contact_title') }}</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    @if ($officeHours)
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Office hours</dt><dd class="text-slate-800">{{ $officeHours }}</dd></div>
                    @endif
                    @if ($school->phone)
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</dt><dd><a href="tel:{{ $school->phone }}" class="text-brand underline-offset-2 hover:underline">{{ $school->phone }}</a></dd></div>
                    @endif
                    @if ($school->email)
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</dt><dd><a href="mailto:{{ $school->email }}" class="text-brand underline-offset-2 hover:underline">{{ $school->email }}</a></dd></div>
                    @endif
                    @if ($school->address)
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Address</dt><dd class="text-slate-800">{{ $school->address }}</dd></div>
                    @endif
                </dl>
            </article>
        </div>
    </section>
</x-layouts.public>
