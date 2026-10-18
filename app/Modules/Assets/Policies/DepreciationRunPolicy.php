<?php

namespace App\Modules\Assets\Policies;

use App\Models\User;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Assets\Models\DepreciationRun;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;

/**
 * Runs are posted when created and only cancelled afterwards (the latest
 * one first).
 */
class DepreciationRunPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Depreciation Run';
    }

    public function view(User $user): bool
    {
        return $this->hasPermission($user, 'View List');
    }

    public function update(User $user): bool
    {
        return false;
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function cancel(User $user, DepreciationRun $run): bool
    {
        return $run->status === DocumentStatus::Posted && $this->hasPermission($user, 'Cancel');
    }
}
