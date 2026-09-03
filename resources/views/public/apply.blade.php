<x-layouts.public :school="$school" title="Apply for admission">
    <section class="section--ink py-16 sm:py-20">
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            <p class="eyebrow eyebrow--onDark" data-aos="fade-up">Admissions {{ now()->year }} / {{ now()->year + 1 }}</p>

            <h1 class="mt-4 font-display text-3xl font-bold text-white sm:text-4xl lg:text-5xl"
                data-aos="fade-up" data-aos-delay="80">Apply for admission</h1>

            <p class="mt-4 text-white/75" data-aos="fade-up" data-aos-delay="160">
                Complete the four steps below. Nothing is submitted until you review and confirm on the
                last step, and you will receive an application number to track your submission.
            </p>
        </div>
    </section>

    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4" role="alert">
                <p class="text-sm font-semibold text-rose-800">Please correct the following</p>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-rose-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{--
            A four-step wizard. Every field stays in one form so a single POST
            carries the whole application; Alpine only controls which step is
            visible, and the browser's own validation runs before advancing.
        --}}
        <form
            method="POST"
            action="{{ route('apply.store') }}"
            enctype="multipart/form-data"
            x-data="{
                step: 1,
                total: 4,
                next() {
                    const panel = this.$refs['step' + this.step];
                    const invalid = [...panel.querySelectorAll('input, select, textarea')]
                        .find(field => ! field.checkValidity());

                    if (invalid) {
                        invalid.reportValidity();
                        return;
                    }

                    this.step = Math.min(this.step + 1, this.total);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
                back() {
                    this.step = Math.max(this.step - 1, 1);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
            }"
        >
            @csrf

            {{-- Progress: each bar fills from the left as its step is reached. --}}
            <ol class="mb-10 grid grid-cols-4 gap-2.5" aria-label="Application progress">
                @foreach (['Student', 'Guardian', 'Documents', 'Review'] as $index => $label)
                    <li class="text-center">
                        <div class="h-1.5 overflow-hidden rounded-full bg-slate-200">
                            <div
                                class="h-full origin-left rounded-full bg-brand transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
                                :class="step >= {{ $index + 1 }} ? 'scale-x-100' : 'scale-x-0'"
                            ></div>
                        </div>

                        <span
                            class="mt-2.5 flex items-center justify-center gap-1.5 text-xs font-medium transition-colors duration-300"
                            :class="step >= {{ $index + 1 }} ? 'text-slate-900' : 'text-slate-400'"
                        >
                            <span
                                class="grid size-5 place-items-center rounded-full text-[10px] font-bold transition-all duration-300"
                                :class="step > {{ $index + 1 }}
                                    ? 'bg-brand text-white'
                                    : (step === {{ $index + 1 }} ? 'bg-brand/15 text-brand ring-2 ring-brand/30' : 'bg-slate-100 text-slate-400')"
                                aria-hidden="true"
                            >
                                <span x-show="step > {{ $index + 1 }}">&check;</span>
                                <span x-show="step <= {{ $index + 1 }}">{{ $index + 1 }}</span>
                            </span>
                            <span class="hidden sm:inline">{{ $label }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>

            {{-- Step 1: student --}}
            <div x-ref="step1" x-show="step === 1"
                x-transition:enter="transition duration-450 ease-[cubic-bezier(0.22,1,0.36,1)]"
                x-transition:enter-start="opacity-0 translate-x-6"
                x-transition:enter-end="opacity-100 translate-x-0">
                <x-ui.card title="Student information" description="Details of the child applying.">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.field label="First name" name="student_first_name" required>
                            <x-ui.input name="student_first_name" required />
                        </x-ui.field>

                        <x-ui.field label="Middle name" name="student_middle_name">
                            <x-ui.input name="student_middle_name" />
                        </x-ui.field>

                        <x-ui.field label="Last name" name="student_last_name" required>
                            <x-ui.input name="student_last_name" required />
                        </x-ui.field>

                        <x-ui.field label="Gender" name="gender">
                            <x-ui.select name="gender" placeholder="Select" :options="['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other']" />
                        </x-ui.field>

                        <x-ui.field label="Date of birth" name="date_of_birth">
                            <x-ui.input name="date_of_birth" type="date" />
                        </x-ui.field>

                        <x-ui.field label="Place of birth" name="place_of_birth">
                            <x-ui.input name="place_of_birth" />
                        </x-ui.field>

                        <x-ui.field label="Nationality" name="nationality">
                            <x-ui.input name="nationality" placeholder="Liberian" />
                        </x-ui.field>

                        <x-ui.field label="Class applying for" name="intended_class">
                            <x-ui.select
                                name="intended_class"
                                placeholder="Select a class"
                                :options="collect(range(7, 12))->mapWithKeys(fn ($g) => ['Grade '.$g => 'Grade '.$g])->all()"
                            />
                        </x-ui.field>
                    </div>
                </x-ui.card>

                <x-ui.card title="Previous school" description="Leave blank if this is the child's first school." class="mt-6">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.field label="Previous school" name="previous_school">
                            <x-ui.input name="previous_school" />
                        </x-ui.field>

                        <x-ui.field label="Last class completed" name="previous_class">
                            <x-ui.input name="previous_class" />
                        </x-ui.field>

                        <x-ui.field label="Academic year" name="academic_year">
                            <x-ui.input name="academic_year" placeholder="{{ now()->year }} / {{ now()->year + 1 }}" />
                        </x-ui.field>
                    </div>
                </x-ui.card>
            </div>

            {{-- Step 2: guardian --}}
            <div x-ref="step2" x-show="step === 2" x-cloak
                x-transition:enter="transition duration-450 ease-[cubic-bezier(0.22,1,0.36,1)]"
                x-transition:enter-start="opacity-0 translate-x-6"
                x-transition:enter-end="opacity-100 translate-x-0">
                <x-ui.card title="Parent or guardian" description="How the school will reach you.">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.field label="Full name" name="guardian_name" required>
                            <x-ui.input name="guardian_name" required />
                        </x-ui.field>

                        <x-ui.field label="Relationship to student" name="guardian_relationship">
                            <x-ui.select
                                name="guardian_relationship"
                                placeholder="Select"
                                :options="['Mother' => 'Mother', 'Father' => 'Father', 'Guardian' => 'Guardian', 'Other' => 'Other']"
                            />
                        </x-ui.field>

                        <x-ui.field label="Phone number" name="guardian_phone" required>
                            <x-ui.input name="guardian_phone" type="tel" required placeholder="+231 77 000 0000" />
                        </x-ui.field>

                        <x-ui.field label="Email address" name="guardian_email">
                            <x-ui.input name="guardian_email" type="email" />
                        </x-ui.field>

                        <x-ui.field label="Occupation" name="guardian_occupation">
                            <x-ui.input name="guardian_occupation" />
                        </x-ui.field>

                        <x-ui.field label="Emergency contact" name="emergency_contact" hint="A second person we can call.">
                            <x-ui.input name="emergency_contact" />
                        </x-ui.field>

                        <x-ui.field label="Home address" name="guardian_address" class="sm:col-span-2">
                            <x-ui.textarea name="guardian_address" rows="3" />
                        </x-ui.field>
                    </div>
                </x-ui.card>
            </div>

            {{-- Step 3: documents --}}
            <div x-ref="step3" x-show="step === 3" x-cloak
                x-transition:enter="transition duration-450 ease-[cubic-bezier(0.22,1,0.36,1)]"
                x-transition:enter-start="opacity-0 translate-x-6"
                x-transition:enter-end="opacity-100 translate-x-0">
                <x-ui.card
                    title="Supporting documents"
                    description="PDF, JPG or PNG, up to 5 MB each. You can submit without these and send them later."
                >
                    <div class="space-y-4">
                        @foreach ($documentTypes as $index => $type)
                            <div class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-center">
                                <div>
                                    <label for="document-{{ $index }}" class="block text-sm font-medium text-slate-700">
                                        {{ $type }}
                                    </label>
                                    <input type="hidden" name="documents[{{ $index }}][type]" value="{{ $type }}">
                                    <input
                                        type="file"
                                        name="documents[{{ $index }}][file]"
                                        id="document-{{ $index }}"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        class="mt-1.5 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                                    >
                                </div>
                            </div>

                            @error("documents.{$index}.file")
                                <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        @endforeach
                    </div>
                </x-ui.card>
            </div>

            {{-- Step 4: review --}}
            <div x-ref="step4" x-show="step === 4" x-cloak
                x-transition:enter="transition duration-450 ease-[cubic-bezier(0.22,1,0.36,1)]"
                x-transition:enter-start="opacity-0 translate-x-6"
                x-transition:enter-end="opacity-100 translate-x-0">
                <x-ui.card title="Review and submit" description="Check the details below before submitting.">
                    <p class="text-sm text-slate-600">
                        By submitting, you confirm that the information you have provided is accurate.
                        {{ $school->name }} will review the application and contact you on the phone
                        number or email address you supplied.
                    </p>

                    <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                        <p class="font-medium text-slate-800">What happens next</p>
                        <ol class="mt-2 list-inside list-decimal space-y-1">
                            <li>You receive an application number immediately.</li>
                            <li>The registrar verifies your documents.</li>
                            <li>You are contacted about the outcome or any missing information.</li>
                        </ol>
                    </div>

                    <div class="mt-6">
                        <x-ui.button type="submit" class="w-full">Submit application</x-ui.button>
                    </div>
                </x-ui.card>
            </div>

            {{-- Navigation --}}
            <div class="mt-6 flex items-center justify-between gap-3">
                <x-ui.button type="button" variant="secondary" x-show="step > 1" x-cloak @click="back()">
                    Back
                </x-ui.button>
                <span x-show="step === 1"></span>

                <x-ui.button type="button" x-show="step < total" @click="next()">
                    Continue
                </x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.public>
