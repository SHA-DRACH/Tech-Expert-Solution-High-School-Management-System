{{-- The school's own letterhead, logo on both sides as on the paper template. --}}
@php
    $logo = $school?->logo_path ? Storage::disk('public')->url($school->logo_path) : null;
@endphp

<div class="school">
    @if ($logo)
        <img class="logo" src="{{ $logo }}" alt="">
    @else
        <span class="logo-text">{{ $school?->initials() }}</span>
    @endif

    <div>
        <h1>{{ $school?->name }}</h1>
        @if ($school?->address)
            <p>{{ $school->address }}</p>
        @endif
        @if ($school?->phone)
            <p>Contact#: {{ $school->phone }}</p>
        @endif
        @if ($school?->email)
            <p>Email Address: {{ $school->email }}</p>
        @endif
    </div>

    @if ($logo)
        <img class="logo" src="{{ $logo }}" alt="">
    @else
        <span class="logo-text">{{ $school?->initials() }}</span>
    @endif
</div>
