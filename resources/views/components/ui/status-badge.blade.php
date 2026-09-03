@props(['status'])

@php
    $tone = match ($status) {
        'active', 'approved', 'verified', 'enrolled', 'graduated' => 'success',
        'pending', 'submitted', 'under_review', 'documents_required', 'interview_required', 'requires_correction' => 'warning',
        'rejected', 'suspended', 'cancelled', 'withdrawn' => 'danger',
        'transferred', 'archived', 'draft' => 'neutral',
        default => 'info',
    };
@endphp

<x-ui.badge :tone="$tone">{{ Str::headline($status) }}</x-ui.badge>
