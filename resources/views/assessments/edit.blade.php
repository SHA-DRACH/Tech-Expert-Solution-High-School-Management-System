<x-layouts.app title="Edit assessment" heading="Edit assessment">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        'Assessments' => route('assessments.index'),
        $assessment->title => route('assessments.scores', $assessment),
        'Edit' => null,
    ]" />

    <x-ui.page-header
        :title="$assessment->title"
        :description="$assessment->subject?->name.' · '.$assessment->section?->full_name"
    />

    <form method="POST" action="{{ route('assessments.update', $assessment) }}" enctype="multipart/form-data" class="max-w-2xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Details">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Title" name="title" required class="sm:col-span-2">
                    <x-ui.input name="title" :value="$assessment->title" required />
                </x-ui.field>

                <x-ui.field label="Question or instructions" name="instructions" class="sm:col-span-2"
                            hint="What the students are being asked to do. Shown to them and to their parents.">
                    <x-ui.textarea name="instructions" rows="5" :value="$assessment->instructions"
                                   placeholder="Answer all three questions. Show your working." />
                </x-ui.field>

                <x-ui.field label="Question paper" name="question_paper" class="sm:col-span-2"
                            :hint="$assessment->attachment_path
                                ? 'Uploading a new file replaces the one attached now.'
                                : 'Optional. PDF or Word document, up to 8 MB. Students and parents can download it.'">
                    @if ($assessment->attachment_path)
                        <p class="mb-2 text-sm text-slate-600">
                            Attached:
                            <a href="{{ route('assessments.question', $assessment) }}"
                               class="font-medium text-brand hover:underline">{{ $assessment->attachment_name }}</a>
                        </p>
                    @endif

                    <input type="file" name="question_paper" id="question_paper" accept=".pdf,.doc,.docx"
                           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                </x-ui.field>

                <x-ui.field label="Type" name="type" required>
                    <x-ui.select name="type" :selected="$assessment->type"
                                 :options="collect($types)->mapWithKeys(fn ($t) => [$t => Str::headline($t)])->all()" />
                </x-ui.field>

                <x-ui.field label="Marked out of" name="max_score" required>
                    <x-ui.input name="max_score" type="number" min="1" max="1000" :value="$assessment->max_score" required />
                </x-ui.field>

                <x-ui.field label="Weight" name="weight">
                    <x-ui.input name="weight" type="number" step="0.1" min="0.1" max="100" :value="$assessment->weight" />
                </x-ui.field>

                <x-ui.field label="Opens at" name="starts_at" hint="When students may start. Leave blank for no fixed start.">
                    <x-ui.input name="starts_at" type="datetime-local"
                                :value="$assessment->starts_at?->format('Y-m-d\TH:i')" />
                </x-ui.field>

                <x-ui.field label="Closes at" name="ends_at" hint="The deadline. A submission after this is marked late.">
                    <x-ui.input name="ends_at" type="datetime-local"
                                :value="$assessment->ends_at?->format('Y-m-d\TH:i')" />
                </x-ui.field>

                <x-ui.field label="Term" name="term_id">
                    <x-ui.select name="term_id" :selected="$assessment->term_id" placeholder="Not set"
                                 :options="$terms->mapWithKeys(fn ($t) => [$t->id => $t->name])->all()" />
                </x-ui.field>

                <x-ui.field label="Part of an examination" name="examination_id">
                    <x-ui.select name="examination_id" :selected="$assessment->examination_id" placeholder="Not part of one"
                                 :options="$examinations->mapWithKeys(fn ($e) => [$e->id => $e->name])->all()" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <div class="flex items-center justify-between gap-2">
            @can('delete', $assessment)
                <x-ui.confirm
                    :action="route('assessments.destroy', $assessment)"
                    method="DELETE"
                    title="Delete this assessment?"
                    message="The assessment and any marks entered against it will be removed. This cannot be undone."
                    confirm="Delete assessment"
                    class="text-rose-600 hover:bg-rose-50"
                >Delete</x-ui.confirm>
            @else
                <span></span>
            @endcan

            <div class="flex items-center gap-2">
                <x-ui.button :href="route('assessments.scores', $assessment)" variant="secondary">Cancel</x-ui.button>
                <x-ui.button type="submit">Save changes</x-ui.button>
            </div>
        </div>
    </form>
</x-layouts.app>
