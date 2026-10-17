<?php

namespace App\Modules\Core\Enums;

/**
 * The roles the system relies on. Additional roles can be created from the admin panel.
 */
enum UserRole: string
{
    case SuperAdmin = 'Super Admin';
    case Admin = 'Admin';
    case Customer = 'Customer';

    /**
     * Determine whether the role is protected from being renamed, edited or deleted.
     */
    public static function isProtected(string $roleName): bool
    {
        return self::tryFrom($roleName) !== null;
    }
}
