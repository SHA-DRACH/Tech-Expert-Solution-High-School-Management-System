<x-layouts.app title="Examinations" heading="Examinations">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Examinations' => null]" />

    <x-ui.page-header
        title="Examinations & grading"
        description="Examination periods, and the grade boundaries this school awards."
    />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Examination periods" :padded="false">
                @if ($examinations->isEmpty())
                    <x-ui.empty-state icon="◈" title="Nothing scheduled" description="Schedule an examination period to group assessments under it." />
                @else
                    <x-ui.table :headings="['Examination', 'Term', 'Dates', 'Assessments', 'Status']">
                        @foreach ($examinations as $examination)
                            <tr>
                                <td class="px-5 py-3">
                                    <span class="font-medium text-slate-900">{{ $examination->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ Str::headline($examination->type) }}</span>
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $examination->term?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-xs text-slate-600">
                                    {{ $examination->starts_on?->format('j M') }}
                                    @if ($examination->ends_on) – {{ $examination->ends_on->format('j M Y') }} @endif
                                </td>
                                <td class="px-5 py-3 tabular-nums text-slate-600">{{ $examination->assessments_count }}</td>
                                <td class="px-5 py-3"><x-ui.status-badge :status="$examination->status" /></td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>

            @can('exams.manage')
                {{--
                    The grade scale is edited as a whole because the bands must
                    cover 0-100 with no gaps; the server rejects a set that does not.
                --}}
                <x-ui.card title="Grading scale" description="Every letter awarded anywhere in the system comes from these bands.">
                    <form method="POST" action="{{ route('examinations.scale') }}"
                          x-data="{ bands: {{ Js::from($scale->map(fn ($b) => [
                              'grade' => $b->grade,
                              'min_score' => $b->min_score,
                              'max_score' => $b->max_score,
                              'remark' => $b->remark,
                              'points' => $b->points,
                          ])->values()) }} }">
                        @csrf
                        @method('PUT')

                        @error('bands')
                            <p class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $message }}</p>
                        @enderror

                        <div class="space-y-2">
                            <div class="grid grid-cols-[4rem_5rem_5rem_1fr_5rem_2rem] gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <span>Grade</span><span>From</span><span>To</span><span>Remark</span><span>Points</span><span></span>
                            </div>

                            <template x-for="(band, index) in bands" :key="index">
                                <div class="grid grid-cols-[4rem_5rem_5rem_1fr_5rem_2rem] items-center gap-2">
                                    <input type="text" :name="`bands[${index}][grade]`" x-model="band.grade" maxlength="5" required
                                           class="rounded-lg border-0 px-2 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand">
                                    <input type="number" :name="`bands[${index}][min_score]`" x-model="band.min_score" min="0" max="100" required
                                           class="rounded-lg border-0 px-2 py-1.5 text-sm tabular-nums shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand">
                                    <input type="number" :name="`bands[${index}][max_score]`" x-model="band.max_score" min="0" max="100" required
                                           class="rounded-lg border-0 px-2 py-1.5 text-sm tabular-nums shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand">
                                    <input type="text" :name="`bands[${index}][remark]`" x-model="band.remark" maxlength="60"
                                           class="rounded-lg border-0 px-2 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand">
                                    <input type="number" :name="`bands[${index}][points]`" x-model="band.points" step="0.1" min="0" max="10"
                                           class="rounded-lg border-0 px-2 py-1.5 text-sm tabular-nums shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand">
                                    <button type="button" @click="bands.splice(index, 1)"
                                            class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                            aria-label="Remove band">&times;</button>
                                </div>
                            </template>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-2">
                            <x-ui.button type="button" variant="secondary" size="sm"
                                         @click="bands.push({ grade: '', min_score: 0, max_score: 0, remark: '', points: null })">
                                Add a band
                            </x-ui.button>

                            <x-ui.button type="submit">Save grading scale</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            @endcan
        </div>

        @can('exams.manage')
            <x-ui.card title="Schedule an examination">
                <form method="POST" action="{{ route('examinations.store') }}" class="space-y-4">
                    @csrf

                    <x-ui.field label="Name" name="name" required>
                        <x-ui.input name="name" required placeholder="Mid-Term Examinations" />
                    </x-ui.field>

                    <x-ui.field label="Type" name="type" required>
                        <x-ui.select name="type" required
                                     :options="collect($types)->mapWithKeys(fn ($t) => [$t => Str::headline($t)])->all()" />
                    </x-ui.field>

                    <x-ui.field label="Term" name="term_id">
                        <x-ui.select name="term_id" placeholder="Not tied to a term"
                                     :options="$terms->mapWithKeys(fn ($t) => [$t->id => $t->name])->all()" />
                    </x-ui.field>

                    <x-ui.field label="Starts on" name="starts_on">
                        <x-ui.input name="starts_on" type="date" />
                    </x-ui.field>

                    <x-ui.field label="Ends on" name="ends_on">
                        <x-ui.input name="ends_on" type="date" />
                    </x-ui.field>

                    <x-ui.button type="submit" class="w-full">Schedule examination</x-ui.button>
                </form>
            </x-ui.card>
        @endcan
    </div>
</x-layouts.app>
