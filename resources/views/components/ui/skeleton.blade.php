@props(['rows' => 3])

{{-- Placeholder shown while content is still on its way. --}}
<div class="space-y-3 px-5 py-4" aria-hidden="true">
    @for ($i = 0; $i < $rows; $i++)
        <div class="flex items-center gap-4">
            <div class="shimmer size-9 shrink-0 rounded-full"></div>
            <div class="flex-1 space-y-2">
                <div class="shimmer h-3 w-1/3 rounded"></div>
                <div class="shimmer h-3 w-1/2 rounded"></div>
            </div>
        </div>
    @endfor
</div>
