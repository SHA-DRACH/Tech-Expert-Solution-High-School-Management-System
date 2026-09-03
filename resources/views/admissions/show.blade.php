<x-layouts.app :title="$admission->application_number" :heading="$admission->student_name">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Admissions' => route('admissions.index'), $admission->application_number => null]" />

    <x-ui.page-header :title="$admission->student_name" :description="$admission->application_number">
        <x-slot:actions>
            <x-ui.status-badge :status="$admission->status" />
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Applicant">
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    @foreach ([
                        'Full name' => $admission->student_name,
                        'Gender' => $admission->gender,
                        'Date of birth' => $admission->date_of_birth?->format('j F Y'),
                        'Place of birth' => $admission->place_of_birth,
                        'Nationality' => $admission->nationality,
                        'Intended class' => $admission->intended_class,
                        'Academic year' => $admission->academic_year,
                        'Previous school' => $admission->previous_school,
                        'Previous class' => $admission->previous_class,
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-ui.card>

            <x-ui.card title="Parent or guardian">
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    @foreach ([
                        'Name' => $admission->guardian_name,
                        'Relationship' => $admission->guardian_relationship,
                        'Phone' => $admission->guardian_phone,
                        'Email' => $admission->guardian_email,
                        'Occupation' => $admission->guardian_occupation,
                        'Emergency contact' => $admission->emergency_contact,
                        'Address' => $admission->guardian_address,
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-ui.card>

            <x-ui.card
                title="Submitted documents"
                description="Documents are stored privately and only downloadable by authorised staff."
                :padded="false"
            >
                @forelse ($admission->documents as $document)
                    <div class="border-b border-slate-100 px-5 py-4 last:border-0">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900">{{ $document->document_type }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $document->original_name }}</p>
                                @if ($document->review_note)
                                    <p class="mt-1 text-xs text-amber-700">{{ $document->review_note }}</p>
                                @endif
                                @if ($document->reviewed_at)
                                    <p class="mt-1 text-xs text-slate-400">
                                        Reviewed by {{ $document->reviewer?->name ?? 'a staff member' }}
                                        on {{ $document->reviewed_at->format('j M Y') }}
                                    </p>
                                @endif
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <x-ui.status-badge :status="$document->status" />
                                <x-ui.button
                                    :href="route('admissions.documents.download', $document)"
                                    variant="secondary"
                                    size="sm"
                                >Download</x-ui.button>
                            </div>
                        </div>

                        @can('verify', $document)
                            <form method="POST" action="{{ route('admissions.documents.review', $document) }}"
                                  class="mt-3 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-3">
                                @csrf
                                @method('PATCH')

                                <div class="w-48">
                                    <label for="status-{{ $document->id }}" class="sr-only">Document status</label>
                                    <select name="status" id="status-{{ $document->id }}"
                                            class="block w-full rounded-lg border-0 py-2 pl-3 pr-9 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand">
                                        @foreach ($documentStatuses as $status)
                                            <option value="{{ $status }}" @selected($document->status === $status)>
                                                {{ Str::headline($status) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="min-w-48 flex-1">
                                    <label for="note-{{ $document->id }}" class="sr-only">Reason or note</label>
                                    <input type="text" name="review_note" id="note-{{ $document->id }}"
                                           value="{{ $document->review_note }}"
                                           placeholder="Reason, e.g. please upload a clearer copy"
                                           class="block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand">
                                </div>

                                <x-ui.button type="submit" variant="secondary" size="sm">Save review</x-ui.button>
                            </form>
                        @endcan
                    </div>
                @empty
                    <x-ui.empty-state
                        icon="🗎"
                        title="No documents submitted"
                        description="The applicant did not attach any supporting documents."
                    />
                @endforelse
            </x-ui.card>
        </div>

        <div class="space-y-6">
            @can('review', $admission)
                <x-ui.card title="Review decision" description="Move the application through the admission workflow.">
                    <form method="POST" action="{{ route('admissions.update', $admission) }}" class="space-y-5">
                        @csrf
                        @method('PATCH')

                        <x-ui.field label="Status" name="status" required>
                            <x-ui.select
                                name="status"
                                :selected="$admission->status"
                                :options="collect($reviewStatuses)->mapWithKeys(fn ($s) => [$s => Str::headline($s)])->all()"
                            />
                        </x-ui.field>

                        <x-ui.field label="Review notes" name="review_notes" hint="Internal notes for other reviewers.">
                            <x-ui.textarea name="review_notes" :value="$admission->review_notes" rows="5" />
                        </x-ui.field>

                        <x-ui.button type="submit" class="w-full">Save review</x-ui.button>
                    </form>
                </x-ui.card>
            @endcan

            <x-ui.card title="Timeline">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Submitted</dt>
                        <dd class="mt-0.5">{{ $admission->created_at->format('j F Y, H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Last updated</dt>
                        <dd class="mt-0.5">{{ $admission->updated_at->diffForHumans() }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
