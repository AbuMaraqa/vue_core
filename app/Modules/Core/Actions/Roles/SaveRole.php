<?php

namespace App\Modules\Core\Actions\Roles;

use App\Modules\Core\Data\RoleData;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class SaveRole
{
    /**
     * Create or update a role together with its permissions, and record it in the activity log.
     */
    public function handle(Role $role, RoleData $data): Role
    {
        return DB::transaction(function () use ($role, $data): Role {
            $event = $role->exists ? 'updated' : 'created';

            $role->fill(['name' => $data->name, 'guard_name' => 'web'])->save();

            $role->syncPermissions($data->permissions);

            activity('roles')
                ->performedOn($role)
                ->event($event)
                ->withProperties(['permissions' => $data->permissions])
                ->log($event);

            return $role;
        });
    }
}
