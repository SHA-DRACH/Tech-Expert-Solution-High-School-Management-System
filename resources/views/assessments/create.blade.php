<x-layouts.app title="New assessment" heading="New assessment">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Assessments' => route('assessments.index'), 'New' => null]" />

    <x-ui.page-header title="Set an assessment" description="You will enter the marks on the next screen." />

    <form method="POST" action="{{ route('assessments.store') }}" enctype="multipart/form-data" class="max-w-2xl space-y-6">
        @csrf

        <x-ui.card title="What is being assessed">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Class" name="section_id" required>
                    <x-ui.select name="section_id" required placeholder="Select a class"
                                 :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" />
                </x-ui.field>

                <x-ui.field label="Subject" name="subject_id" required>
                    <x-ui.select name="subject_id" required placeholder="Select a subject"
                                 :options="$subjects->mapWithKeys(fn ($s) => [$s->id => $s->name])->all()" />
                </x-ui.field>

                <x-ui.field label="Title" name="title" required class="sm:col-span-2">
                    <x-ui.input name="title" required placeholder="First Class Test" />
                </x-ui.field>

                <x-ui.field label="Question or instructions" name="instructions" class="sm:col-span-2"
                            hint="What the students are being asked to do. Shown to them and to their parents.">
                    <x-ui.textarea name="instructions" rows="5"
                                   placeholder="Answer all three questions. Show your working." />
                </x-ui.field>

                <x-ui.field label="Question paper" name="question_paper" class="sm:col-span-2"
                            hint="Optional. PDF or Word document, up to 8 MB. Students and parents can download it.">
                    <input type="file" name="question_paper" id="question_paper" accept=".pdf,.doc,.docx"
                           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                </x-ui.field>

                <x-ui.field label="Type" name="type" required>
                    <x-ui.select name="type" required
                                 :options="collect($types)->mapWithKeys(fn ($t) => [$t => Str::headline($t)])->all()" />
                </x-ui.field>

                <x-ui.field label="Marked out of" name="max_score" required>
                    <x-ui.input name="max_score" type="number" min="1" max="1000" :value="100" required />
                </x-ui.field>

                <x-ui.field label="Weight" name="weight"
                            hint="How much this counts towards the subject average. 1 is normal.">
                    <x-ui.input name="weight" type="number" step="0.1" min="0.1" max="100" :value="1" />
                </x-ui.field>

                <x-ui.field label="Opens at" name="starts_at" hint="When students may start. Leave blank for no fixed start.">
                    <x-ui.input name="starts_at" type="datetime-local" />
                </x-ui.field>

                <x-ui.field label="Closes at" name="ends_at" hint="The deadline. A submission after this is marked late.">
                    <x-ui.input name="ends_at" type="datetime-local" />
                </x-ui.field>

                <x-ui.field label="Term" name="term_id">
                    <x-ui.select name="term_id" placeholder="Current term"
                                 :options="$terms->mapWithKeys(fn ($t) => [$t->id => $t->name])->all()" />
                </x-ui.field>

                <x-ui.field label="Part of an examination" name="examination_id">
                    <x-ui.select name="examination_id" placeholder="Not part of one"
                                 :options="$examinations->mapWithKeys(fn ($e) => [$e->id => $e->name])->all()" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <div class="flex items-center justify-end gap-2">
            <x-ui.button :href="route('assessments.index')" variant="secondary">Cancel</x-ui.button>
            <x-ui.button type="submit">Create and enter marks</x-ui.button>
        </div>
    </form>
</x-layouts.app>
