<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Actions\Roles\SaveRole;
use App\Modules\Core\Data\RoleData;
use App\Modules\Core\Http\Requests\Roles\StoreRoleRequest;
use App\Modules\Core\Http\Requests\Roles\UpdateRoleRequest;
use App\Modules\Core\Http\Resources\RoleResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('id')
            ->get();

        return Inertia::render('admin/roles/Index', [
            'roles' => RoleResource::collection($roles),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Role::class);

        return Inertia::render('admin/roles/Create', [
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(StoreRoleRequest $request, SaveRole $saveRole): RedirectResponse
    {
        $saveRole->handle(new Role, RoleData::from($request->validated()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('admin.roles.index');
    }

    public function edit(Role $role): Response
    {
        Gate::authorize('update', $role);

        return Inertia::render('admin/roles/Edit', [
            'role' => RoleResource::make($role->load('permissions')),
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role, SaveRole $saveRole): RedirectResponse
    {
        $saveRole->handle($role, RoleData::from($request->validated()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('admin.roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $role->delete();

        activity('roles')->event('deleted')->withProperties(['name' => $role->name])->log('deleted');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('admin.roles.index');
    }

    /**
     * Get the permissions grouped by module, in the order of the PermissionSeeder.
     *
     * @return list<array{module: string, permissions: list<string>}>
     */
    protected function permissionGroups(): array
    {
        $groups = Permission::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Permission $permission): string => $permission->module_name ?? 'Other')
            ->map(fn (Collection $permissions, string $module): array => [
                'module' => $module,
                'permissions' => array_values($permissions->map(fn (Permission $permission): string => $permission->name)->all()),
            ]);

        return array_values($groups->all());
    }
}
