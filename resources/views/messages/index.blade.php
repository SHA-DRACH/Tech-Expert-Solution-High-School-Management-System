<x-layouts.app title="Messages" heading="Messages">
    <x-ui.page-header
        title="Messages"
        description="Conversations between families and the school."
    />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" :padded="false" title="Conversations">
            @if ($threads->isEmpty())
                <x-ui.empty-state
                    icon="✉"
                    title="No conversations yet"
                    description="Start one using the form beside this list."
                />
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($threads as $thread)
                        @php $unread = $thread->hasUnreadFor(auth()->user()); @endphp

                        <a href="{{ route('messages.show', $thread) }}"
                           class="flex items-start gap-3 px-5 py-4 transition-colors hover:bg-slate-50">
                            <span @class([
                                'mt-1.5 size-2 shrink-0 rounded-full',
                                'bg-brand' => $unread,
                                'bg-transparent' => ! $unread,
                            ])
                            @if ($unread) aria-label="Unread" @endif></span>

                            <span class="min-w-0 flex-1">
                                <span @class([
                                    'block truncate text-sm',
                                    'font-semibold text-slate-900' => $unread,
                                    'font-medium text-slate-800' => ! $unread,
                                ])>{{ $thread->subject }}</span>

                                <span class="mt-0.5 block truncate text-xs text-slate-500">
                                    {{ $thread->participants->where('id', '!=', auth()->id())->pluck('name')->join(', ') }}
                                    @if ($thread->student) · about {{ $thread->student->full_name }} @endif
                                </span>
                            </span>

                            <span class="shrink-0 text-right">
                                <span class="block text-[11px] text-slate-400">
                                    {{ $thread->last_message_at?->diffForHumans() }}
                                </span>
                                @if ($thread->status === 'closed')
                                    <x-ui.badge>Closed</x-ui.badge>
                                @endif
                            </span>
                        </a>
                    @endforeach
                </div>

                <div class="border-t border-slate-100 px-5 py-3">{{ $threads->links() }}</div>
            @endif
        </x-ui.card>

        <x-ui.card title="New message">
            @if ($recipients->isEmpty())
                <p class="text-sm text-slate-500">
                    There is nobody you can message yet. Once classes and teachers are assigned,
                    they will appear here.
                </p>
            @else
                <form method="POST" action="{{ route('messages.store') }}" class="space-y-4">
                    @csrf

                    <x-ui.field label="To" name="recipient_id" required>
                        <x-ui.select name="recipient_id" required placeholder="Select a recipient"
                                     :options="$recipients->mapWithKeys(fn ($r) => [$r->id => $r->name])->all()" />
                    </x-ui.field>

                    @if ($students->isNotEmpty())
                        <x-ui.field label="About which student" name="student_id" hint="Optional.">
                            <x-ui.select name="student_id" placeholder="Not about a specific student"
                                         :options="$students->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" />
                        </x-ui.field>
                    @endif

                    <x-ui.field label="Subject" name="subject" required>
                        <x-ui.input name="subject" required placeholder="Short summary" />
                    </x-ui.field>

                    <x-ui.field label="Message" name="body" required>
                        <x-ui.textarea name="body" rows="5" required />
                    </x-ui.field>

                    <x-ui.button type="submit" class="w-full">Send message</x-ui.button>
                </form>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
