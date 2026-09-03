{{-- Shared by the desktop sidebar and the mobile drawer. --}}
{{-- `portal` rather than `dashboard`: it forwards each account to the home it
     actually has, so a teacher clicking the school logo lands on their own
     workspace instead of the school-wide dashboard. --}}
<a href="{{ route('portal') }}" class="group mb-8 flex items-center gap-3 px-2">
    @if ($school?->logo_path)
        <img src="{{ Storage::disk('public')->url($school->logo_path) }}" alt=""
             class="size-10 rounded-xl object-cover transition-transform duration-300 group-hover:scale-105">
    @else
        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand text-lg font-bold text-white
                     transition-transform duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)] group-hover:scale-105 group-hover:rotate-3">
            {{ $school?->initials() ?? 'G' }}
        </span>
    @endif

    <span class="min-w-0">
        <strong class="block truncate text-sm text-white transition-colors">{{ $school?->short_name ?? $school?->name ?? 'GSMS' }}</strong>
        <span class="block truncate text-xs">{{ $school ? 'School workspace' : 'Platform' }}</span>
    </span>
</a>

@php
    /*
     | Which groups start open.
     |
     | The group holding the page you are on always does - collapsing the thing
     | someone just clicked would lose their place. Everything else starts shut,
     | which is the point: the sidebar was long enough to scroll, and scrolling
     | to find a link you use every day is worse than one extra click.
     |
     | Worked out here rather than in Alpine so it is right in the very first
     | painted frame, with no flicker of everything-open before JavaScript runs,
     | and so the correct group is open for someone with JavaScript off.
     */
    $groups = [];

    foreach ($navigation as $group => $items) {
        /*
         | `when` covers entries that depend on who someone is rather than what
         | they may do; it defaults to true so existing entries are unaffected.
         |
         | A null `can` means no permission is needed. Parents and students hold
         | no permission slugs at all, so without this their whole menu -
         | including their own profile and notifications - filtered itself away
         | to nothing.
         */
        $visible = array_values(array_filter(
            $items,
            fn ($item) => ($item['when'] ?? true)
                && (($item['can'] ?? null) === null || auth()->user()->hasPermission($item['can'])),
        ));

        if ($visible === []) {
            continue;
        }

        $groups[] = [
            'label' => $group,
            'items' => $visible,
            'active' => collect($visible)->contains(fn ($item) => request()->routeIs($item['active'])),
        ];
    }

    // Nothing matched — a page outside the menu — so open the first group
    // rather than presenting a wall of closed headings with no way in.
    if ($groups !== [] && ! collect($groups)->contains('active', true)) {
        $groups[0]['active'] = true;
    }
@endphp

<nav class="flex-1 space-y-1.5 overflow-y-auto">
    @foreach ($groups as $index => $group)
        <div x-data="{ open: {{ $group['active'] ? 'true' : 'false' }} }" class="enter-left"
             style="animation-delay: {{ $index * 60 }}ms">
            <button
                type="button"
                x-on:click="open = ! open"
                x-bind:aria-expanded="open ? 'true' : 'false'"
                class="group/head flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2
                       text-[11px] font-semibold uppercase tracking-widest text-slate-500
                       transition-colors hover:bg-white/5 hover:text-slate-300
                       focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
            >
                <span class="flex items-center gap-2">
                    {{ $group['label'] }}

                    {{-- How many links are hidden, so a shut group is not a
                         mystery. Only while shut, or it just repeats itself. --}}
                    <span x-show="! open"
                          class="rounded-full bg-white/10 px-1.5 py-0.5 text-[10px] font-medium tracking-normal text-slate-400">
                        {{ count($group['items']) }}
                    </span>
                </span>

                <svg class="size-3.5 shrink-0 transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
                     x-bind:class="open ? 'rotate-90' : ''"
                     viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M4.5 2.5 8 6l-3.5 3.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            {{-- x-collapse animates the height; x-cloak keeps a shut group from
                 flashing open before Alpine takes over. --}}
            <div x-show="open" x-collapse @if (! $group['active']) x-cloak @endif>
                <div class="mt-1 space-y-1 pb-2 text-sm">
                    @foreach ($group['items'] as $item)
                        @php $isActive = request()->routeIs($item['active']); @endphp

                        <a
                            href="{{ route($item['route']) }}"
                            class="group/nav relative flex items-center gap-3 overflow-hidden rounded-lg px-3 py-2.5
                                   transition-all duration-250 ease-[cubic-bezier(0.16,1,0.3,1)]
                                   {{ $isActive ? 'bg-white/10 text-white' : 'hover:bg-white/5 hover:pl-4 hover:text-white' }}"
                            @if ($isActive) aria-current="page" @endif
                        >
                            {{-- Active marker slides in from the left edge. --}}
                            <span
                                aria-hidden="true"
                                class="absolute inset-y-1.5 left-0 w-0.5 rounded-full bg-brand transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]
                                       {{ $isActive ? 'scale-y-100' : 'scale-y-0 group-hover/nav:scale-y-50' }}"
                            ></span>

                            <span aria-hidden="true"
                                  class="w-4 text-center transition-transform duration-300 group-hover/nav:scale-110">{{ $item['icon'] }}</span>

                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</nav>

@can('viewAny', App\Models\School::class)
    <div class="mt-6 border-t border-white/10 pt-5">
        <a href="{{ route('platform.schools') }}"
           class="group/nav flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-all duration-250 hover:bg-white/5 hover:pl-4 hover:text-white">
            <span aria-hidden="true" class="w-4 text-center transition-transform duration-300 group-hover/nav:rotate-90">⬡</span>
            <span>Platform schools</span>
        </a>
    </div>
@endcan
