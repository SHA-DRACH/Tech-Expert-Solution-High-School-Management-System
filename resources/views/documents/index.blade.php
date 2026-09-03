<x-layouts.app title="Documents" heading="Documents">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Documents' => null]" />

    <x-ui.page-header
        title="Documents"
        description="Certificates, transcripts, contracts and anything else the school files. Stored privately and never linked to directly."
    />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Filed" :value="(string) $documents->total()" note="Matching the filters below" />
        <x-ui.stat label="Awaiting verification" :value="(string) $pendingCount" note="Not yet checked" />
        <x-ui.stat label="Expiring soon" :value="(string) $expiringCount" note="Within the next month" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-ui.card :padded="false">
                <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
                    <div class="min-w-48 flex-1">
                        <label for="search" class="sr-only">Search documents</label>
                        <x-ui.input name="search" :value="$filters['search']" placeholder="Search by title or file name…" />
                    </div>

                    <div class="w-44">
                        <label for="status" class="sr-only">Status</label>
                        <x-ui.select name="status" :selected="$filters['status']" placeholder="All statuses"
                                     :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => Str::headline($s)])->all()" />
                    </div>

                    <div class="w-48">
                        <label for="type" class="sr-only">Type</label>
                        <x-ui.select name="type" :selected="$filters['type']" placeholder="All types"
                                     :options="$types->mapWithKeys(fn ($t) => [$t->id => $t->name])->all()" />
                    </div>

                    <label class="flex items-center gap-2 pb-2">
                        <input type="checkbox" name="expiring" value="1" @checked($filters['expiring'])
                               class="size-4 rounded border-slate-300 text-brand focus:ring-brand">
                        <span class="text-sm text-slate-600">Expiring</span>
                    </label>

                    <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>

                    @if (array_filter($filters))
                        <x-ui.button :href="route('documents.index')" variant="ghost">Clear</x-ui.button>
                    @endif
                </form>

                @if ($documents->isEmpty())
                    <x-ui.empty-state
                        icon="🗎"
                        title="{{ array_filter($filters) ? 'Nothing matches those filters' : 'No documents filed yet' }}"
                        description="{{ array_filter($filters) ? 'Try a different search or status.' : 'Upload a certificate, transcript or contract against a student or teacher.' }}"
                    />
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($documents as $document)
                            <div class="px-5 py-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-medium text-slate-900">{{ $document->title }}</p>
                                            <x-ui.status-badge :status="$document->status" />

                                            @if ($document->isExpired())
                                                <x-ui.badge tone="danger">Expired</x-ui.badge>
                                            @elseif ($document->isExpiringSoon())
                                                <x-ui.badge tone="warning">Expiring soon</x-ui.badge>
                                            @endif
                                        </div>

                                        <p class="mt-0.5 text-xs text-slate-500">
                                            {{ $document->type?->name ?? 'Uncategorised' }} ·
                                            {{ class_basename($document->documentable_type) }}:
                                            {{ $document->documentable?->full_name ?? 'record removed' }}
                                        </p>

                                        <p class="mt-1 text-[11px] text-slate-400">
                                            {{ $document->original_name }} · {{ $document->humanSize() }}
                                            @if ($document->expires_on) · expires {{ $document->expires_on->format('j M Y') }} @endif
                                            · filed by {{ $document->uploader?->name ?? 'unknown' }}
                                            {{ $document->created_at->diffForHumans() }}
                                        </p>

                                        @if ($document->review_note)
                                            <p class="mt-1.5 text-xs text-amber-700">{{ $document->review_note }}</p>
                                        @endif
                                    </div>

                                    <div class="flex shrink-0 items-center gap-1">
                                        @can('download', $document)
                                            <x-ui.button :href="route('documents.download', $document)"
                                                         variant="secondary" size="sm">Download</x-ui.button>
                                        @endcan

                                        @can('delete', $document)
                                            <x-ui.confirm
                                                :action="route('documents.destroy', $document)"
                                                method="DELETE"
                                                title="Remove this document?"
                                                :message="'&quot;'.$document->title.'&quot; will be removed from the file. The record is kept so it can be restored if this was a mistake.'"
                                                confirm="Remove document"
                                                class="text-rose-600 hover:bg-rose-50"
                                            >Remove</x-ui.confirm>
                                        @endcan
                                    </div>
                                </div>

                                @can('verify', $document)
                                    <form method="POST" action="{{ route('documents.verify', $document) }}"
                                          class="mt-3 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-3">
                                        @csrf
                                        @method('PATCH')

                                        <div class="w-48">
                                            <label for="status-{{ $document->id }}" class="sr-only">Verification status</label>
                                            <select name="status" id="status-{{ $document->id }}"
                                                    class="block w-full rounded-lg border-0 py-2 pl-3 pr-9 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand">
                                                @foreach ($statuses as $status)
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
                        @endforeach
                    </div>

                    <div class="border-t border-slate-100 px-5 py-3">{{ $documents->links() }}</div>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-6">
            @can('create', App\Models\Document::class)
                <x-ui.card title="File a document" description="Uploads are private and start as unverified.">
                    <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data"
                          class="space-y-4" x-data="{ subject: 'student' }">
                        @csrf

                        <x-ui.field label="Belongs to" name="subject" required>
                            <select name="subject" id="subject" x-model="subject" required
                                    class="block w-full rounded-lg border-0 py-2 pl-3 pr-9 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand">
                                <option value="student">A student</option>
                                <option value="teacher">A teacher</option>
                            </select>
                        </x-ui.field>

                        {{-- The id field is a plain number so this form stays
                             usable in a school with thousands of students. --}}
                        <x-ui.field label="Record number" name="subject_id" required
                                    hint="The student or staff number's record id, shown on their profile page.">
                            <x-ui.input name="subject_id" type="number" min="1" required />
                        </x-ui.field>

                        <x-ui.field label="Document type" name="document_type_id">
                            <x-ui.select name="document_type_id" placeholder="Uncategorised"
                                         :options="$types->mapWithKeys(fn ($t) => [$t->id => $t->name.' ('.($subjects[$t->applies_to] ?? $t->applies_to).')'])->all()" />
                        </x-ui.field>

                        <x-ui.field label="Title" name="title" required>
                            <x-ui.input name="title" required placeholder="Birth certificate" />
                        </x-ui.field>

                        <x-ui.field label="Issued on" name="issued_on">
                            <x-ui.input name="issued_on" type="date" />
                        </x-ui.field>

                        <x-ui.field label="Expires on" name="expires_on" hint="Leave blank if it does not expire.">
                            <x-ui.input name="expires_on" type="date" />
                        </x-ui.field>

                        <x-ui.field label="File" name="file" required hint="PDF, image or Word, up to 8 MB.">
                            <input type="file" name="file" id="file" required
                                   accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                        </x-ui.field>

                        <x-ui.button type="submit" class="w-full">Upload document</x-ui.button>
                    </form>
                </x-ui.card>
            @endcan

            @can('settings.manage')
                <x-ui.card title="Document types" description="What this school asks for.">
                    @error('document_type')
                        <p class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                            {{ $message }}
                        </p>
                    @enderror

                    <div class="mb-4 space-y-1.5">
                        @forelse ($types as $type)
                            <div class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-50">
                                <div class="min-w-0">
                                    <p class="truncate text-sm text-slate-800">
                                        {{ $type->name }}
                                        @if ($type->is_required)
                                            <span class="text-rose-500" title="Required">*</span>
                                        @endif
                                    </p>
                                    <p class="text-[11px] text-slate-400">
                                        {{ $subjects[$type->applies_to] ?? $type->applies_to }}
                                        @if ($type->expires) · expires @endif
                                    </p>
                                </div>

                                <form method="POST" action="{{ route('documents.types.destroy', $type) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="grid size-7 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                            aria-label="Remove {{ $type->name }}">&times;</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No types defined yet.</p>
                        @endforelse
                    </div>

                    <form method="POST" action="{{ route('documents.types.store') }}" class="space-y-3 border-t border-slate-100 pt-4">
                        @csrf

                        <x-ui.field label="Name" name="name" required>
                            <x-ui.input name="name" required placeholder="Medical certificate" />
                        </x-ui.field>

                        <x-ui.field label="Applies to" name="applies_to" required>
                            <x-ui.select name="applies_to" :options="$subjects" />
                        </x-ui.field>

                        <div class="flex flex-wrap gap-4">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_required" value="1"
                                       class="size-4 rounded border-slate-300 text-brand focus:ring-brand">
                                <span class="text-sm text-slate-600">Required</span>
                            </label>

                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="expires" value="1"
                                       class="size-4 rounded border-slate-300 text-brand focus:ring-brand">
                                <span class="text-sm text-slate-600">Expires</span>
                            </label>
                        </div>

                        <x-ui.button type="submit" variant="secondary" size="sm" class="w-full">Add type</x-ui.button>
                    </form>
                </x-ui.card>
            @endcan
        </div>
    </div>
</x-layouts.app>
