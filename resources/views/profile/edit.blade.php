@php
    $isTeacher = $teacher !== null;
@endphp

{{-- Everyone gets the same shell. There used to be a second, top-bar layout for
     parents and students, which meant they saw a different application from the
     one their school used and had no side navigation at all. --}}
<x-layouts.app title="My account" heading="My account">
    <x-ui.page-header
        title="My account"
        description="Your own details, your password, and what this account is allowed to do."
    />

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="min-w-0 space-y-6">
            @if (! $canEditDetails)
                {{-- Read-only rather than hidden: a student should still be
                     able to check that the school has their name and email
                     right, even where they may not change them themselves. --}}
                <x-ui.card title="Your details"
                           description="Your school looks after these. Ask the office if anything is wrong.">
                    @if ($canViewDetails)
                        <dl class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Full name</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ $user->name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Email address</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ $user->email }}</dd>
                            </div>
                        </dl>
                    @else
                        <p class="text-sm text-slate-500">
                            Your school has not made your details visible here. You can still change your password below.
                        </p>
                    @endif
                </x-ui.card>
            @else
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')

                <x-ui.card title="Your details">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.field label="Full name" name="name" required>
                            <x-ui.input name="name" :value="$user->name" required />
                        </x-ui.field>

                        <x-ui.field label="Email address" name="email" required hint="This is what you sign in with.">
                            <x-ui.input name="email" type="email" :value="$user->email" required />
                        </x-ui.field>

                        @if ($isTeacher)
                            <x-ui.field label="Phone number" name="phone" class="sm:col-span-2"
                                        hint="Kept on your staff record, which is what the rest of the school reads.">
                                <x-ui.input name="phone" type="tel" :value="$teacher->phone" />
                            </x-ui.field>
                        @endif
                    </div>

                    <div class="mt-6 flex justify-end border-t border-slate-100 pt-4">
                        <x-ui.button type="submit">Save details</x-ui.button>
                    </div>
                </x-ui.card>
            </form>
            @endif

            <form method="POST" action="{{ route('profile.password') }}">
                @csrf
                @method('PUT')

                <x-ui.card
                    title="Password"
                    description="Changing your password signs out your other sessions."
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.field label="Current password" name="current_password" required class="sm:col-span-2"
                                    hint="Asked for even though you are signed in, so an unattended screen cannot be used to take the account over.">
                            <x-ui.input name="current_password" type="password" autocomplete="current-password" :remember="false" required />
                        </x-ui.field>

                        <x-ui.field label="New password" name="password" required hint="At least 12 characters.">
                            <x-ui.input name="password" type="password" autocomplete="new-password" :remember="false" required />
                        </x-ui.field>

                        <x-ui.field label="Confirm new password" name="password_confirmation" required>
                            <x-ui.input name="password_confirmation" type="password" autocomplete="new-password" :remember="false" required />
                        </x-ui.field>
                    </div>

                    <div class="mt-6 flex justify-end border-t border-slate-100 pt-4">
                        <x-ui.button type="submit">Change password</x-ui.button>
                    </div>
                </x-ui.card>
            </form>

            @if ($isTeacher)
                <form method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data">
                    @csrf

                    <x-ui.card title="Photograph"
                               description="Appears on the staff list, and on the public website if your profile is published.">
                        <div class="flex flex-wrap items-center gap-5">
                            @if ($teacher->photo_path)
                                <img src="{{ Storage::disk('public')->url($teacher->photo_path) }}" alt=""
                                     class="size-20 rounded-full object-cover">
                            @else
                                <span class="grid size-20 place-items-center rounded-full bg-brand/10 font-display text-xl font-bold text-brand">
                                    {{ Str::substr($teacher->first_name, 0, 1) }}{{ Str::substr($teacher->last_name, 0, 1) }}
                                </span>
                            @endif

                            <div class="min-w-56 flex-1">
                                <x-ui.field label="New photograph" name="photo" hint="JPG, PNG or WebP, up to 2 MB.">
                                    <input type="file" name="photo" id="photo" accept="image/*"
                                           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                                </x-ui.field>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end border-t border-slate-100 pt-4">
                            <x-ui.button type="submit">Upload photograph</x-ui.button>
                        </div>
                    </x-ui.card>
                </form>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card title="This account">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Status</dt>
                        <dd><x-ui.status-badge :status="$user->status" /></dd>
                    </div>

                    <div class="flex items-start justify-between gap-3">
                        <dt class="shrink-0 text-slate-500">Roles</dt>
                        <dd class="flex flex-wrap justify-end gap-1">
                            @forelse ($user->roles as $role)
                                <x-ui.badge tone="info">{{ $role->name }}</x-ui.badge>
                            @empty
                                <span class="text-xs text-slate-400">None</span>
                            @endforelse
                        </dd>
                    </div>

                    @if ($isTeacher)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Staff number</dt>
                            <dd class="font-medium text-slate-900">{{ $teacher->staff_number }}</dd>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Department</dt>
                            <dd class="font-medium text-slate-900">{{ $teacher->department?->name ?? '—' }}</dd>
                        </div>
                    @endif
                </dl>

                <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    Your roles are set by an administrator. If you need to reach something you cannot,
                    that is what to ask them to change.
                </p>
            </x-ui.card>

            @if ($children->isNotEmpty())
                <x-ui.card title="Your children">
                    <ul class="space-y-2 text-sm">
                        @foreach ($children as $child)
                            <li class="flex items-center justify-between gap-2">
                                <span class="font-medium text-slate-900">{{ $child->full_name }}</span>
                                <span class="text-xs text-slate-500">{{ $child->student_number }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            <x-ui.card title="What you can do"
                       description="The list the system itself enforces, not a description of it.">
                @if ($permissions->isEmpty())
                    <p class="text-sm text-slate-500">
                        This account reaches its own portal only, which is normal for a parent or student account.
                    </p>
                @else
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($permissions as $permission)
                            <span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-700" title="{{ $permission->slug }}">
                                {{ $permission->name }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
