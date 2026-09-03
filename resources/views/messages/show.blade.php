<x-layouts.app :title="$thread->subject" heading="Conversation">
    <x-ui.breadcrumbs :trail="['Messages' => route('messages.index'), $thread->subject => null]" />

    <x-ui.page-header
        :title="$thread->subject"
        :description="$thread->participants->pluck('name')->join(', ').($thread->student ? ' · about '.$thread->student->full_name : '')"
    >
        <x-slot:actions>
            <form method="POST" action="{{ route('messages.close', $thread) }}">
                @csrf
                <x-ui.button type="submit" variant="secondary">
                    {{ $thread->status === 'closed' ? 'Reopen' : 'Close' }} conversation
                </x-ui.button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padded="false">
        <div class="space-y-4 p-5">
            @foreach ($thread->messages as $message)
                @php $mine = $message->user_id === $user->id; @endphp

                <div @class(['flex gap-3', 'flex-row-reverse' => $mine])>
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                        {{ Str::of($message->author?->name ?? '?')->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('') }}
                    </span>

                    <div @class(['max-w-lg', 'text-right' => $mine])>
                        <div @class([
                            'rounded-2xl px-4 py-2.5 text-sm',
                            'bg-brand text-white' => $mine,
                            'bg-slate-100 text-slate-800' => ! $mine,
                        ])>
                            <p class="whitespace-pre-line text-left">{{ $message->body }}</p>
                        </div>

                        <p class="mt-1 text-[11px] text-slate-400">
                            {{ $message->author?->name }} · {{ $message->created_at->diffForHumans() }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($thread->status === 'closed')
            <p class="border-t border-slate-100 px-5 py-4 text-center text-sm text-slate-500">
                This conversation is closed. Reopen it to reply.
            </p>
        @else
            <form method="POST" action="{{ route('messages.reply', $thread) }}"
                  class="border-t border-slate-100 px-5 py-4">
                @csrf

                <x-ui.field label="Reply" name="body" required>
                    <x-ui.textarea name="body" rows="3" required placeholder="Write your reply..." />
                </x-ui.field>

                <div class="mt-3 flex justify-end">
                    <x-ui.button type="submit">Send reply</x-ui.button>
                </div>
            </form>
        @endif
    </x-ui.card>
</x-layouts.app>
