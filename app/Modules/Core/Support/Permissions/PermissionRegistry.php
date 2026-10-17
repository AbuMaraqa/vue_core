<?php

namespace App\Modules\Core\Support\Permissions;

use App\Modules\Core\Contracts\PermissionGroup;

/**
 * Collects the permission groups every module registers, so the database,
 * the roles screen and the seeders all share a single source of truth.
 */
class PermissionRegistry
{
    /**
     * @var array<class-string<PermissionGroup>, class-string<PermissionGroup>>
     */
    protected array $groups = [];

    /**
     * Register one or more permission group enums.
     *
     * @param  class-string<PermissionGroup>  ...$groups
     */
    public function register(string ...$groups): static
    {
        foreach ($groups as $group) {
            $this->groups[$group] = $group;
        }

        return $this;
    }

    /**
     * Get the names of every registered permission.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_merge(...array_map(
            fn (array $group): array => array_column($group['permissions'], 'name'),
            $this->grouped(),
        ));
    }

    /**
     * Get the registered permissions grouped for display. Labels are
     * translation keys, translated by the frontend.
     *
     * @return list<array{key: string, label: string, permissions: list<array{name: string, label: string}>}>
     */
    public function grouped(): array
    {
        return array_values(array_map(fn (string $group): array => [
            'key' => class_basename($group),
            'label' => $group::groupLabel(),
            'permissions' => array_map(fn (PermissionGroup $permission): array => [
                'name' => $permission->permissionName(),
                'label' => $permission->label(),
            ], $group::cases()),
        ], $this->groups));
    }
}
