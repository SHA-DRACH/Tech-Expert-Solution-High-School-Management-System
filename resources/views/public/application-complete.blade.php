<x-layouts.public :school="$school" title="Application submitted">
    <div class="mx-auto max-w-2xl px-4 py-20 text-center sm:px-6">
        <span class="mx-auto grid size-14 place-items-center rounded-full bg-emerald-50 text-2xl text-emerald-600" aria-hidden="true">&check;</span>

        <h1 class="mt-6 text-3xl font-semibold tracking-tight">Application submitted</h1>
        <p class="mt-3 text-slate-600">
            Thank you. Your application to {{ $school->name }} has been received and is now with our
            admissions team.
        </p>

        <div class="mt-8 rounded-xl border border-slate-200 bg-slate-50 px-6 py-8">
            <p class="text-xs font-medium uppercase tracking-widest text-slate-500">Your application number</p>
            <p class="mt-2 font-mono text-2xl font-semibold tracking-tight text-slate-900">{{ $applicationNumber }}</p>
            <p class="mt-3 text-sm text-slate-600">
                Please write this number down. You will need it whenever you contact the school about
                this application.
            </p>
        </div>

        <div class="mt-8 text-left">
            <h2 class="text-sm font-semibold text-slate-900">What happens next</h2>
            <ol class="mt-3 space-y-3 text-sm text-slate-600">
                <li class="flex gap-3">
                    <span class="grid size-6 shrink-0 place-items-center rounded-full bg-white text-xs font-semibold text-slate-700 ring-1 ring-slate-200">1</span>
                    <span>The registrar reviews your application and verifies the documents you uploaded.</span>
                </li>
                <li class="flex gap-3">
                    <span class="grid size-6 shrink-0 place-items-center rounded-full bg-white text-xs font-semibold text-slate-700 ring-1 ring-slate-200">2</span>
                    <span>If anything is unclear or missing, we will contact you to request a correction.</span>
                </li>
                <li class="flex gap-3">
                    <span class="grid size-6 shrink-0 place-items-center rounded-full bg-white text-xs font-semibold text-slate-700 ring-1 ring-slate-200">3</span>
                    <span>Once a decision is made, you will be contacted on the phone number or email address you gave us.</span>
                </li>
            </ol>
        </div>

        <div class="mt-10 flex flex-wrap justify-center gap-3">
            <x-ui.button :href="route('home')" variant="secondary">Back to the website</x-ui.button>
            <x-ui.button :href="route('public.contact')">Contact the office</x-ui.button>
        </div>
    </div>
</x-layouts.public>
