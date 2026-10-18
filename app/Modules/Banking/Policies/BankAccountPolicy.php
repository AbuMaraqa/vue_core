<?php

namespace App\Modules\Banking\Policies;

use App\Models\User;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;

class BankAccountPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Bank Account';
    }

    /**
     * An account money has moved through stays: deactivate it instead.
     */
    public function delete(User $user, BankAccount $bankAccount): bool
    {
        if ($this->hasJournalLines($bankAccount)) {
            return false;
        }

        return $this->hasPermission($user, 'Delete');
    }

    public function reconcile(User $user): bool
    {
        return $this->hasPermission($user, 'Reconcile');
    }

    /**
     * Read `journal_lines_exists` when the list loaded the account with
     * `withExists('journalLines')`, else ask the database.
     */
    protected function hasJournalLines(BankAccount $bankAccount): bool
    {
        $account = $bankAccount->account;

        if (array_key_exists('journal_lines_exists', $account->getAttributes())) {
            return (bool) $account->getAttribute('journal_lines_exists');
        }

        return $account->journalLines()->exists();
    }
}
