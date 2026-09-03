@php
    $child = null;

@endphp

<x-layouts.app title="Requests">
    <x-ui.page-header
        title="Requests"
        description="Ask a question, request a meeting or raise a concern with the school office."
    />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-1" title="New request">
            <form method="POST" action="{{ route('parent.requests.store') }}" class="space-y-5">
                @csrf

                <x-ui.field label="Type of request" name="type" required>
                    <x-ui.select name="type" :options="$types" placeholder="Select a type" required />
                </x-ui.field>

                @if ($children->isNotEmpty())
                    <x-ui.field label="About which child" name="student_id" hint="Leave blank for a general enquiry.">
                        <x-ui.select
                            name="student_id"
                            placeholder="Not about a specific child"
                            :options="$children->mapWithKeys(fn ($c) => [$c->id => $c->full_name])->all()"
                        />
                    </x-ui.field>
                @endif

                <x-ui.field label="Subject" name="subject" required>
                    <x-ui.input name="subject" required placeholder="Short summary" />
                </x-ui.field>

                <x-ui.field label="Message" name="body" required>
                    <x-ui.textarea name="body" rows="5" required placeholder="Tell the school what you need." />
                </x-ui.field>

                <x-ui.button type="submit" class="w-full">Send request</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card class="lg:col-span-2" title="Your requests" :padded="false">
            @if ($requests->isEmpty())
                <x-ui.empty-state icon="✉" title="No requests yet" description="Anything you send appears here with the school's reply." />
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($requests as $request)
                        <div class="px-5 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-900">{{ $request->subject }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $request->typeLabel() }}
                                        @if ($request->student) · {{ $request->student->full_name }} @endif
                                        · {{ $request->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                <x-ui.status-badge :status="$request->status" />
                            </div>

                            <p class="mt-2 text-sm text-slate-600">{{ $request->body }}</p>

                            @if ($request->response)
                                <div class="mt-3 rounded-lg border-l-2 border-brand bg-slate-50 p-3">
                                    <p class="text-xs font-semibold text-slate-700">School reply</p>
                                    <p class="mt-1 text-sm text-slate-600">{{ $request->response }}</p>
                                    <p class="mt-1 text-[11px] text-slate-400">
                                        {{ $request->responder?->name }} · {{ $request->responded_at?->format('j M Y') }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-slate-100 px-5 py-3">{{ $requests->links() }}</div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
