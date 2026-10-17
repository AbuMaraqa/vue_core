<?php

namespace App\Modules\Core\Contracts;

use BackedEnum;

/**
 * A backed enum that groups the permissions of a single feature.
 *
 * Each case value is the permission name stored in the database, so it can be
 * passed directly to Gate checks such as `$user->can(UserPermission::Create)`.
 */
interface PermissionGroup extends BackedEnum
{
    /**
     * The translatable label of the group shown on the roles screen.
     */
    public static function groupLabel(): string;

    /**
     * The name of the permission as stored in the database.
     */
    public function permissionName(): string;

    /**
     * The translatable label of a single permission.
     */
    public function label(): string;
}
