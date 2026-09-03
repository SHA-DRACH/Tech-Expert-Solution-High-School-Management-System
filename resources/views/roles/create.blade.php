<x-layouts.app title="Create role" heading="Create role">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Roles & permissions' => route('roles.index'), 'Create role' => null]" />

    <x-ui.page-header
        title="Create a role"
        description="Give the role a name, then choose exactly what it may do."
    />

    <div class="max-w-4xl">
        <x-role-form
            :catalogue="$catalogue"
            :assigned="$assigned"
            :action="route('roles.store')"
        />
    </div>
</x-layouts.app>
