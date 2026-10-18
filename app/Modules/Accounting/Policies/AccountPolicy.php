<?php

namespace App\Modules\Accounting\Policies;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Settings\AccountingSettings;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;

class AccountPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Account';
    }

    /**
     * Accounts with postings, children or an automatic role stay: deactivate
     * them instead, so the history remains intact. Lists preload the checks
     * with `withExists(['journalLines', 'children'])`.
     */
    public function delete(User $user, Account $account): bool
    {
        $hasPostings = $account->journal_lines_exists ?? $account->journalLines()->exists();
        $hasChildren = $account->children_exists ?? $account->children()->exists();

        if ($hasPostings || $hasChildren) {
            return false;
        }

        if (in_array($account->id, app(AccountingSettings::class)->default_accounts, true)) {
            return false;
        }

        return $this->hasPermission($user, 'Delete');
    }
}
