<x-layouts.app title="Report cards" heading="Report cards">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Report cards' => null]" />

    <x-ui.page-header
        title="Report cards"
        description="Built from approved marks and attendance. Drafts stay hidden until you publish them."
    />

    @if ($canGenerate)
        <div class="mb-6 grid gap-6 lg:grid-cols-2">
            <x-ui.card title="Generate" description="Builds drafts for one class and term. Safe to re-run.">
                <form method="POST" action="{{ route('reportcards.generate') }}" class="flex flex-wrap items-end gap-3">
                    @csrf

                    <div class="min-w-44 flex-1">
                        <x-ui.field label="Class" name="section_id" required>
                            <x-ui.select name="section_id" required placeholder="Select a class"
                                         :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" />
                        </x-ui.field>
                    </div>

                    <div class="min-w-36 flex-1">
                        <x-ui.field label="Term" name="term_id" required>
                            <x-ui.select name="term_id" required placeholder="Select a term"
                                         :options="$terms->mapWithKeys(fn ($t) => [$t->id => $t->name])->all()" />
                        </x-ui.field>
                    </div>

                    <x-ui.button type="submit">Generate drafts</x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.card title="Publish" description="Makes drafts visible to parents and students.">
                <form method="POST" action="{{ route('reportcards.publish') }}" class="flex flex-wrap items-end gap-3">
                    @csrf

                    <div class="min-w-44 flex-1">
                        <x-ui.field label="Class" name="section_id" required>
                            <x-ui.select name="section_id" required placeholder="Select a class"
                                         :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" />
                        </x-ui.field>
                    </div>

                    <div class="min-w-36 flex-1">
                        <x-ui.field label="Term" name="term_id" required>
                            <x-ui.select name="term_id" required placeholder="Select a term"
                                         :options="$terms->mapWithKeys(fn ($t) => [$t->id => $t->name])->all()" />
                        </x-ui.field>
                    </div>

                    <x-ui.button type="submit" variant="secondary">Publish drafts</x-ui.button>
                </form>
            </x-ui.card>
        </div>
    @endif

    <x-ui.card :padded="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
            <div class="w-52">
                <label for="section" class="sr-only">Class</label>
                <x-ui.select name="section" :selected="$filters['section']" placeholder="All classes"
                             :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" />
            </div>

            <div class="w-40">
                <label for="term" class="sr-only">Term</label>
                <x-ui.select name="term" :selected="$filters['term']" placeholder="All terms"
                             :options="$terms->mapWithKeys(fn ($t) => [$t->id => $t->name])->all()" />
            </div>

            <div class="w-40">
                <label for="status" class="sr-only">Status</label>
                <x-ui.select name="status" :selected="$filters['status']" placeholder="All statuses"
                             :options="['draft' => 'Draft', 'published' => 'Published']" />
            </div>

            <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>

            @if (array_filter($filters))
                <x-ui.button :href="route('reportcards.index')" variant="ghost">Clear</x-ui.button>
            @endif
        </form>

        @if ($cards->isEmpty())
            <x-ui.empty-state
                icon="🗎"
                title="No report cards yet"
                description="Generate them for a class once its marks have been approved."
            />
        @else
            <x-ui.table :headings="['Student', 'Class', 'Term', 'Average', 'Position', 'Status', '']">
                @foreach ($cards as $card)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3">
                            <span class="font-medium text-slate-900">{{ $card->student?->full_name }}</span>
                            <span class="block font-mono text-xs text-slate-500">{{ $card->student?->student_number }}</span>
                        </td>
                        <td class="px-5 py-3 text-slate-600">{{ $card->section?->full_name }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $card->term?->name }}</td>
                        <td class="px-5 py-3 tabular-nums text-slate-900">{{ $card->average }}%</td>
                        <td class="px-5 py-3 tabular-nums text-slate-600">
                            {{ $card->position ? $card->position.' of '.$card->class_size : '—' }}
                        </td>
                        <td class="px-5 py-3"><x-ui.status-badge :status="$card->status" /></td>
                        <td class="px-5 py-3 text-right">
                            <x-ui.button :href="route('reportcards.show', $card)" variant="ghost" size="sm">Open</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="border-t border-slate-100 px-5 py-3">{{ $cards->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
