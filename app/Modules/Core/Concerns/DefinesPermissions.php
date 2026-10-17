<?php

namespace App\Modules\Core\Concerns;

/**
 * Shared implementation for string backed permission group enums.
 */
trait DefinesPermissions
{
    public function permissionName(): string
    {
        return $this->value;
    }
}
