<?php

namespace App\Modules\Assets\Policies;

use App\Models\User;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;

/**
 * The figures of an asset are fixed once registered; its description
 * (name, location, serial number, cost center) can still be updated.
 */
class AssetPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Asset';
    }

    public function update(User $user, Asset $asset): bool
    {
        return $asset->status !== AssetStatus::Cancelled && $this->hasPermission($user, 'Update');
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function dispose(User $user, Asset $asset): bool
    {
        return $asset->status->isOwned() && $this->hasPermission($user, 'Dispose');
    }

    public function cancel(User $user, Asset $asset): bool
    {
        return $asset->status === AssetStatus::Active
            && ($asset->getAttribute('depreciations_exists') ?? $asset->depreciations()->exists()) === false
            && $this->hasPermission($user, 'Cancel');
    }
}
