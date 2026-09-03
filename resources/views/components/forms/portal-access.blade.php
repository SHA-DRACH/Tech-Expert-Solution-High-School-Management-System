@props(['roles', 'defaultRole' => null, 'email' => null, 'who' => 'this person'])

{{--
    The optional login half of a create-person form.

    One component, used identically by the teacher, student and guardian forms,
    so the three cannot drift into three different shapes — which is what made
    creating people confusing in the first place.

    Leaving it blank creates the person and no account, which is the common case:
    a school enrols a hundred students and hands out a dozen logins.
--}}
<x-ui.card
    title="Portal access (optional)"
    :description="'Give '.$who.' a way to sign in. Leave blank to create the record only — an account can be added later.'"
    x-data="{ wanted: {{ $errors->has('account_password') || $errors->has('account_email') || $errors->has('account_role_id') ? 'true' : 'false' }} }"
>
    <label class="flex cursor-pointer items-start gap-2.5">
        <input type="checkbox" x-model="wanted"
               class="mt-0.5 size-4 rounded border-slate-300 text-brand focus:ring-brand">
        <span class="text-sm text-slate-700">
            Create a login for {{ $who }}
            <span class="block text-xs text-slate-500">
                You set the password here and pass it on. It is not shown again.
            </span>
        </span>
    </label>

    <div x-show="wanted" x-cloak x-collapse>
        <div class="mt-5 grid gap-5 border-t border-slate-100 pt-5 sm:grid-cols-2">
            <x-ui.field label="Login email address" name="account_email" required
                        hint="This is what they sign in with.">
                <x-ui.input name="account_email" type="email" :value="$email" autocomplete="off" />
            </x-ui.field>

            <x-ui.field label="Role" name="account_role_id" required
                        hint="What the account is allowed to do.">
                <x-ui.select
                    name="account_role_id"
                    placeholder="Select a role"
                    :selected="$defaultRole?->id"
                    :options="$roles->mapWithKeys(fn ($role) => [$role->id => $role->name])->all()"
                />
            </x-ui.field>

            <x-ui.field label="Password" name="account_password" required hint="At least 12 characters.">
                <x-ui.input name="account_password" type="password" autocomplete="new-password" :remember="false" />
            </x-ui.field>

            <x-ui.field label="Confirm password" name="account_password_confirmation" required>
                <x-ui.input name="account_password_confirmation" type="password" autocomplete="new-password" :remember="false" />
            </x-ui.field>
        </div>
    </div>
</x-ui.card>
