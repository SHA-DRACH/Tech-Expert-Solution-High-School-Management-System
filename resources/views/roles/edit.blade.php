<x-layouts.app :title="'Edit '.$role->name" :heading="'Edit '.$role->name">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Roles & permissions' => route('roles.index'), $role->name => null]" />

    <x-ui.page-header
        :title="$role->name"
        :description="$role->is_system
            ? 'This is a system role. Its permissions can be changed, but the role itself cannot be removed.'
            : 'Adjust what this role is allowed to do.'"
    />

    <div class="max-w-4xl">
        <x-role-form
            :role="$role"
            :catalogue="$catalogue"
            :assigned="$assigned"
            :action="route('roles.update', $role)"
            method="PUT"
        />
    </div>
</x-layouts.app>
