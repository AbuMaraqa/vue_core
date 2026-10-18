<?php

namespace App\Modules\Accounting\Support;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Settings\AccountingSettings;

/**
 * The accounts the system posts to automatically, as chosen in the
 * accounting settings.
 */
class DefaultAccounts
{
    public function __construct(protected AccountingSettings $settings) {}

    public function get(AccountRole $role): Account
    {
        $id = $this->settings->default_accounts[$role->value] ?? null;
        $account = $id === null ? null : Account::query()->find($id);

        if ($account === null || $account->is_group || ! $account->is_active) {
            throw new AccountingException(__('Choose the :role account in the accounting settings first.', ['role' => $role->label()]));
        }

        return $account;
    }

    public function id(AccountRole $role): int
    {
        return $this->get($role)->id;
    }
}
