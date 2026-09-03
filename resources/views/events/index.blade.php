<x-layouts.app title="Events" heading="Events">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Events' => null]" />

    <x-ui.page-header title="School calendar" description="Meetings, examinations and school occasions." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Upcoming" :padded="false">
                @forelse ($upcoming as $event)
                    <div x-data="{ editing: false }" class="border-b border-slate-100 last:border-0">
                    <div class="flex items-start gap-4 px-5 py-4">
                        <div class="grid size-12 shrink-0 place-items-center rounded-lg bg-brand/8 text-center">
                            <span class="block font-display text-base font-bold leading-none text-brand">{{ $event->starts_at->format('j') }}</span>
                            <span class="block text-[10px] uppercase text-brand/70">{{ $event->starts_at->format('M') }}</span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-medium text-slate-900">{{ $event->title }}</p>
                                <x-ui.badge>{{ Str::headline($event->category) }}</x-ui.badge>
                                @if ($event->is_public)
                                    <x-ui.badge tone="info">On website</x-ui.badge>
                                @endif
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $event->starts_at->format('l, j F Y \a\t H:i') }}
                                {{-- The finish time as well: "when does it end?" is the
                                     second thing a family asks, and it was recorded but
                                     never shown. --}}
                                @if ($event->ends_at)
                                    &ndash;
                                    {{ $event->ends_at->isSameDay($event->starts_at)
                                        ? $event->ends_at->format('H:i')
                                        : $event->ends_at->format('j F Y \a\t H:i') }}
                                @endif
                                @if ($event->location) · {{ $event->location }} @endif
                            </p>
                            @if ($event->description)
                                <p class="mt-1 text-sm text-slate-600">{{ $event->description }}</p>
                            @endif
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            @if ($event->status === 'cancelled')
                                <x-ui.badge tone="danger">Cancelled</x-ui.badge>
                            @endif

                            <x-ui.button size="sm" variant="ghost" x-on:click="editing = ! editing">Edit</x-ui.button>
                        </div>
                    </div>

                    <div x-show="editing" x-cloak x-collapse class="bg-slate-50 px-5 py-4">
                        <form method="POST" action="{{ route('events.update', $event) }}" class="grid gap-4 sm:grid-cols-2">
                            @csrf
                            @method('PUT')

                            <x-ui.field label="Title" name="title" :id="'ev-title-'.$event->id" class="sm:col-span-2">
                                <x-ui.input name="title" :id="'ev-title-'.$event->id" :value="$event->title" :remember="false" />
                            </x-ui.field>

                            <x-ui.field label="Starts at" name="starts_at" :id="'ev-start-'.$event->id">
                                <x-ui.input name="starts_at" type="datetime-local" :id="'ev-start-'.$event->id"
                                            :value="$event->starts_at?->format('Y-m-d\TH:i')" :remember="false" />
                            </x-ui.field>

                            <x-ui.field label="Ends at" name="ends_at" :id="'ev-end-'.$event->id">
                                <x-ui.input name="ends_at" type="datetime-local" :id="'ev-end-'.$event->id"
                                            :value="$event->ends_at?->format('Y-m-d\TH:i')" :remember="false" />
                            </x-ui.field>

                            <x-ui.field label="Category" name="category" :id="'ev-cat-'.$event->id">
                                <x-ui.select name="category" :id="'ev-cat-'.$event->id" :selected="$event->category"
                                             :options="collect(App\Models\Event::CATEGORIES)->mapWithKeys(fn ($c) => [$c => Str::headline($c)])->all()" />
                            </x-ui.field>

                            <x-ui.field label="Status" name="status" :id="'ev-status-'.$event->id">
                                <x-ui.select name="status" :id="'ev-status-'.$event->id" :selected="$event->status"
                                             :options="['scheduled' => 'Scheduled', 'cancelled' => 'Cancelled', 'completed' => 'Completed']" />
                            </x-ui.field>

                            <x-ui.field label="Location" name="location" :id="'ev-loc-'.$event->id" class="sm:col-span-2">
                                <x-ui.input name="location" :id="'ev-loc-'.$event->id" :value="$event->location" :remember="false" />
                            </x-ui.field>

                            <x-ui.field label="Description" name="description" :id="'ev-desc-'.$event->id" class="sm:col-span-2">
                                <x-ui.textarea name="description" rows="2" :value="$event->description" />
                            </x-ui.field>

                            <label class="flex items-center gap-2.5 sm:col-span-2">
                                <input type="checkbox" name="is_public" value="1"
                                       class="size-4 rounded border-slate-300 text-brand focus:ring-brand"
                                       @checked($event->is_public)>
                                <span class="text-sm text-slate-700">Show on the public website</span>
                            </label>

                            <div class="flex flex-wrap items-center gap-2 sm:col-span-2">
                                <x-ui.button type="submit" size="sm">Save event</x-ui.button>

                                {{-- Cancelling keeps it visible and says so; deleting is for
                                     one entered by mistake, which nobody has been told about. --}}
                                @if ($event->status !== 'cancelled')
                                    <x-ui.confirm
                                        :action="route('events.cancel', $event)"
                                        method="POST"
                                        title="Cancel this event?"
                                        :message="'\''.$event->title.'\' will stay on the calendar marked cancelled, so families who already have it in their diary can see it was called off.'"
                                        confirm="Mark cancelled"
                                        variant="secondary"
                                    >Cancel event</x-ui.confirm>
                                @endif

                                <x-ui.confirm
                                    :action="route('events.destroy', $event)"
                                    method="DELETE"
                                    title="Delete this event?"
                                    :message="'\''.$event->title.'\' is removed entirely. If families have already been told about it, cancel it instead so they can see it was called off.'"
                                    confirm="Delete event"
                                >Delete</x-ui.confirm>
                            </div>
                        </form>
                    </div>
                    </div>
                @empty
                    <x-ui.empty-state icon="◷" title="Nothing scheduled" description="Add your first event to the calendar." />
                @endforelse
            </x-ui.card>

            @if ($past->isNotEmpty())
                <x-ui.card title="Past events" :padded="false">
                    @foreach ($past as $event)
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 last:border-0">
                            <div>
                                <p class="text-sm text-slate-700">{{ $event->title }}</p>
                                <p class="text-xs text-slate-500">{{ $event->starts_at->format('j M Y') }}</p>
                            </div>
                            <x-ui.badge>{{ Str::headline($event->category) }}</x-ui.badge>
                        </div>
                    @endforeach
                </x-ui.card>
            @endif
        </div>

        <x-ui.card title="Add an event">
            <form method="POST" action="{{ route('events.store') }}" class="space-y-4">
                @csrf

                <x-ui.field label="Title" name="title" required>
                    <x-ui.input name="title" required />
                </x-ui.field>

                <x-ui.field label="Category" name="category" required>
                    <x-ui.select name="category" required
                                 :options="collect($categories)->mapWithKeys(fn ($c) => [$c => Str::headline($c)])->all()" />
                </x-ui.field>

                <x-ui.field label="Starts at" name="starts_at" required>
                    <x-ui.input name="starts_at" type="datetime-local" required />
                </x-ui.field>

                <x-ui.field label="Ends at" name="ends_at">
                    <x-ui.input name="ends_at" type="datetime-local" />
                </x-ui.field>

                <x-ui.field label="Location" name="location">
                    <x-ui.input name="location" placeholder="School hall" />
                </x-ui.field>

                <x-ui.field label="Description" name="description">
                    <x-ui.textarea name="description" rows="3" />
                </x-ui.field>

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_public" value="1" checked
                           class="size-4 rounded border-slate-300 text-brand focus:ring-brand">
                    <span class="text-sm text-slate-600">Show on the public website</span>
                </label>

                <x-ui.button type="submit" class="w-full">Add event</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>
