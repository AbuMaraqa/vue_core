<?php

namespace App\Modules\Core\Actions\Permissions;

use App\Modules\Core\Support\Permissions\PermissionRegistry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Make the permissions table match the permissions registered by the modules.
 */
class SyncPermissions
{
    public function __construct(
        protected PermissionRegistry $registry,
        protected PermissionRegistrar $registrar,
    ) {}

    /**
     * @return array{created: list<string>, deleted: list<string>}
     */
    public function handle(bool $prune = false, string $guard = 'web'): array
    {
        $registered = $this->registry->names();
        $existing = Permission::query()->where('guard_name', $guard)->pluck('name')->all();

        $created = array_values(array_diff($registered, $existing));

        foreach ($created as $name) {
            Permission::create(['name' => $name, 'guard_name' => $guard]);
        }

        $deleted = $prune ? array_values(array_diff($existing, $registered)) : [];

        if ($deleted !== []) {
            Permission::query()->where('guard_name', $guard)->whereIn('name', $deleted)->delete();
        }

        $this->registrar->forgetCachedPermissions();

        return ['created' => $created, 'deleted' => $deleted];
    }
}
