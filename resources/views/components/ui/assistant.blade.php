@if (config('assistant.enabled', true))
    {{--
        The assistant panel.

        It asks the server, which answers as the signed-in person through the
        same permission checks as every screen — so it can never surface
        something its user could not open directly. Nothing is answered in this
        file; it only shows what came back.
    --}}
    <div
        x-data="{
            open: false,
            asking: false,
            question: '',
            messages: [],
            suggestions: [],
            provider: '',
            loaded: false,

            async load() {
                if (this.loaded) return;
                this.loaded = true;

                try {
                    const response = await fetch('{{ route('assistant.suggestions') }}', {
                        headers: { 'Accept': 'application/json' },
                    });

                    if (! response.ok) return;

                    const data = await response.json();
                    this.suggestions = data.suggestions ?? [];
                    this.provider = data.provider ?? '';
                } catch (error) {
                    // A failed fetch costs the user nothing here: the panel
                    // simply opens without its starting suggestions.
                }
            },

            async ask(text) {
                const question = (text ?? this.question).trim();

                if (question === '' || this.asking) return;

                this.messages.push({ role: 'you', text: question, sources: [] });
                this.question = '';
                this.asking = true;

                this.$nextTick(() => this.scroll());

                try {
                    const response = await fetch('{{ route('assistant.ask') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                        },
                        body: JSON.stringify({ question }),
                    });

                    if (! response.ok) {
                        throw new Error(response.status);
                    }

                    const data = await response.json();

                    this.messages.push({
                        role: 'assistant',
                        text: data.answer,
                        sources: data.sources ?? [],
                    });
                } catch (error) {
                    /*
                     * Reported as a failure to reach the server, never as an
                     * answer. A network error dressed up as a reply is how
                     * someone ends up acting on a number that was never there.
                     */
                    this.messages.push({
                        role: 'assistant',
                        text: 'I could not reach the server just then. Please try again.',
                        sources: [],
                    });
                } finally {
                    this.asking = false;
                    this.$nextTick(() => this.scroll());
                }
            },

            scroll() {
                const log = this.$refs.log;

                if (log) log.scrollTop = log.scrollHeight;
            },
        }"
        x-on:keydown.escape.window="open = false"
        class="print:hidden"
    >
        <button
            type="button"
            x-on:click="open = ! open; load()"
            class="press fixed bottom-5 right-5 z-40 flex items-center gap-2 rounded-full bg-brand px-4 py-3 text-sm font-semibold text-white shadow-lg transition hover:brightness-110"
            :aria-expanded="open"
            aria-label="Open the assistant"
        >
            <span aria-hidden="true">✦</span>
            <span class="hidden sm:inline" x-text="open ? 'Close assistant' : 'Ask the assistant'">Ask the assistant</span>
        </button>

        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
            x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition duration-200 ease-in"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="fixed bottom-20 right-5 z-40 flex max-h-[min(32rem,calc(100vh-7rem))] w-[min(24rem,calc(100vw-2.5rem))] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
            role="dialog"
            aria-label="School assistant"
        >
            <header class="border-b border-slate-100 px-4 py-3">
                <p class="text-sm font-semibold text-slate-900">Assistant</p>
                <p class="mt-0.5 text-xs text-slate-500">
                    Answers from this school's own records, and only what you are allowed to see.
                </p>
            </header>

            <div x-ref="log" class="flex-1 space-y-3 overflow-y-auto px-4 py-3">
                <template x-if="messages.length === 0">
                    <div>
                        <p class="text-sm text-slate-600">
                            Ask me about students, attendance, fees, results or the school calendar.
                        </p>

                        <div class="mt-3 space-y-1.5">
                            <template x-for="suggestion in suggestions" :key="suggestion">
                                <button
                                    type="button"
                                    x-on:click="ask(suggestion)"
                                    class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-left text-sm text-slate-700 transition-colors hover:border-brand/40 hover:bg-slate-50"
                                    x-text="suggestion"
                                ></button>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-for="(message, index) in messages" :key="index">
                    <div :class="message.role === 'you' ? 'text-right' : ''">
                        <div
                            :class="message.role === 'you'
                                ? 'inline-block max-w-[85%] rounded-2xl rounded-br-sm bg-brand px-3 py-2 text-left text-sm text-white'
                                : 'inline-block max-w-[90%] rounded-2xl rounded-bl-sm bg-slate-100 px-3 py-2 text-sm text-slate-800'"
                        >
                            <p class="whitespace-pre-line" x-text="message.text"></p>

                            <template x-if="message.sources.length > 0">
                                <div class="mt-2 flex flex-wrap gap-1.5 border-t border-slate-200 pt-2">
                                    <template x-for="source in message.sources" :key="source.label">
                                        <a
                                            :href="source.href"
                                            class="rounded-md bg-white px-2 py-1 text-xs font-medium text-brand transition hover:underline"
                                            x-text="source.label"
                                        ></a>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="asking">
                    <p class="text-sm text-slate-400">Looking that up…</p>
                </template>
            </div>

            <form x-on:submit.prevent="ask()" class="border-t border-slate-100 p-3">
                <div class="flex items-end gap-2">
                    <label for="assistant-question" class="sr-only">Your question</label>
                    <input
                        id="assistant-question"
                        type="text"
                        x-model="question"
                        :disabled="asking"
                        maxlength="500"
                        placeholder="Ask about your school…"
                        autocomplete="off"
                        class="block w-full rounded-lg border-0 px-3 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand disabled:opacity-60"
                    >
                    <button
                        type="submit"
                        :disabled="asking || question.trim() === ''"
                        class="press shrink-0 rounded-lg bg-brand px-3 py-2 text-sm font-semibold text-white transition hover:brightness-110 disabled:pointer-events-none disabled:opacity-50"
                    >Ask</button>
                </div>

                <p class="mt-2 text-[11px] text-slate-400" x-text="provider"></p>
            </form>
        </div>
    </div>
@endif
