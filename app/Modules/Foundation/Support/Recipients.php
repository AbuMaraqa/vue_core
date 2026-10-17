<?php

namespace App\Modules\Foundation\Support;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;

/**
 * Find who should receive a notification about a part of the system: the
 * users allowed to see it, through a role or directly, plus super admins.
 *
 * ```php
 * Notification::send(Recipients::withPermission('User View List'), new NewUserRegistered($user));
 * ```
 */
final class Recipients
{
    /**
     * @return Collection<int, User>
     */
    public static function withPermission(string $permission): Collection
    {
        $permissionExists = Permission::query()->where('name', $permission)->where('guard_name', 'web')->exists();

        return User::query()
            ->where(function (Builder $query) use ($permission, $permissionExists): void {
                $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', UserRole::SuperAdmin->value));

                if ($permissionExists) {
                    $query->orWhere(fn (Builder $holders) => $holders->permission($permission));
                }
            })
            ->get();
    }
}
