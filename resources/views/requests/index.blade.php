<x-layouts.app title="Parent requests" heading="Parent requests">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Parent requests' => null]" />

    <x-ui.page-header title="Parent requests" :description="$openCount.' awaiting a reply'" />

    <x-ui.card :padded="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
            <div class="w-48">
                <label for="status" class="sr-only">Status</label>
                <x-ui.select name="status" :selected="$status" placeholder="All statuses"
                             :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => Str::headline($s)])->all()" />
            </div>

            <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>

            @if ($status)
                <x-ui.button :href="route('requests.index')" variant="ghost">Clear</x-ui.button>
            @endif
        </form>

        @if ($requests->isEmpty())
            <x-ui.empty-state icon="✉" title="No requests" description="Messages from parents will appear here." />
        @else
            <div class="divide-y divide-slate-100">
                @foreach ($requests as $parentRequest)
                    <div class="px-5 py-4" x-data="{ open: false }">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-medium text-slate-900">{{ $parentRequest->subject }}</p>
                                    <x-ui.badge>{{ $parentRequest->typeLabel() }}</x-ui.badge>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $parentRequest->guardian?->full_name }}
                                    @if ($parentRequest->student) · about {{ $parentRequest->student->full_name }} @endif
                                    · {{ $parentRequest->created_at->diffForHumans() }}
                                </p>
                                <p class="mt-2 text-sm text-slate-600">{{ $parentRequest->body }}</p>

                                @if ($parentRequest->response)
                                    <div class="mt-3 rounded-lg border-l-2 border-brand bg-slate-50 p-3">
                                        <p class="text-xs font-semibold text-slate-700">Your reply</p>
                                        <p class="mt-1 text-sm text-slate-600">{{ $parentRequest->response }}</p>
                                        <p class="mt-1 text-[11px] text-slate-400">
                                            {{ $parentRequest->responder?->name }} · {{ $parentRequest->responded_at?->format('j M Y') }}
                                        </p>
                                    </div>
                                @endif
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <x-ui.status-badge :status="$parentRequest->status" />
                                <x-ui.button type="button" variant="ghost" size="sm" @click="open = ! open"
                                             x-text="open ? 'Close' : 'Respond'">Respond</x-ui.button>
                            </div>
                        </div>

                        <div x-show="open" x-cloak x-collapse class="mt-4 border-t border-slate-100 pt-4">
                            <form method="POST" action="{{ route('requests.respond', $parentRequest) }}" class="space-y-3">
                                @csrf
                                @method('PATCH')

                                <div class="grid gap-3 sm:grid-cols-[12rem_1fr]">
                                    <div>
                                        <label for="status-{{ $parentRequest->id }}" class="sr-only">Status</label>
                                        <select name="status" id="status-{{ $parentRequest->id }}"
                                                class="block w-full rounded-lg border-0 py-2 pl-3 pr-9 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand">
                                            @foreach ($statuses as $option)
                                                <option value="{{ $option }}" @selected($parentRequest->status === $option)>
                                                    {{ Str::headline($option) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label for="response-{{ $parentRequest->id }}" class="sr-only">Reply</label>
                                        <textarea name="response" id="response-{{ $parentRequest->id }}" rows="3"
                                                  placeholder="Write your reply to the parent..."
                                                  class="block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand">{{ $parentRequest->response }}</textarea>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <x-ui.button type="submit" size="sm">Save response</x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-slate-100 px-5 py-3">{{ $requests->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
