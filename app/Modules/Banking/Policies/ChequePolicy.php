<?php

namespace App\Modules\Banking\Policies;

use App\Models\User;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;

/**
 * Cheques are never edited or deleted: every change is a step of their life
 * (`Cheque Manage`), with its own entry, that can be undone.
 */
class ChequePolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Cheque';
    }

    public function update(User $user): bool
    {
        return false;
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function manage(User $user): bool
    {
        return $this->hasPermission($user, 'Manage');
    }
}
