@props(['children', 'selected', 'route' => 'parent.dashboard'])

{{--
    Switching child reloads the current page for that child, so every figure on
    screen belongs to the same person. The link carries only the child id; the
    controller re-checks that this parent is linked to them.
--}}
@if ($children->count() > 1)
    <div class="mb-6">
        <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-slate-500">My children</p>

        <div class="flex flex-wrap gap-2">
            @foreach ($children as $child)
                @php $isActive = $selected?->is($child); @endphp

                <a href="{{ route($route, ['child' => $child->id]) }}"
                   @class([
                       'press flex items-center gap-2.5 rounded-xl border px-3.5 py-2.5 text-sm transition-all duration-200',
                       'border-brand bg-brand/5 text-slate-900 shadow-sm ring-1 ring-brand/30' => $isActive,
                       'border-slate-200 bg-white text-slate-700 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow' => ! $isActive,
                   ])
                   @if ($isActive) aria-current="true" @endif>
                    <span @class([
                        'grid size-8 place-items-center rounded-full text-xs font-semibold transition-colors',
                        'bg-brand text-white' => $isActive,
                        'bg-slate-100 text-slate-600' => ! $isActive,
                    ])>{{ $child->initials() }}</span>

                    <span class="text-left">
                        <span class="block font-medium leading-tight">{{ $child->full_name }}</span>
                        <span class="block font-mono text-[11px] text-slate-500">{{ $child->student_number }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
@elseif ($selected)
    <div class="mb-6 flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
        <span class="grid size-9 place-items-center rounded-full bg-brand text-xs font-semibold text-white">
            {{ $selected->initials() }}
        </span>
        <span>
            <span class="block text-sm font-medium leading-tight">{{ $selected->full_name }}</span>
            <span class="block font-mono text-[11px] text-slate-500">{{ $selected->student_number }}</span>
        </span>
    </div>
@endif
