<?php

namespace App\Modules\Banking\Actions\BankAccounts;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Banking\Data\BankAccountData;
use App\Modules\Banking\Models\BankAccount;
use Illuminate\Support\Facades\DB;

/**
 * Create or update a bank or cash account together with its account in the
 * chart of accounts. A new account is created under the first cash or banks
 * group (e.g. 1102 → 110201); its name follows the bank account's name. A
 * foreign-currency account is restricted to its currency.
 */
class SaveBankAccount
{
    public function __construct(protected Currencies $currencies) {}

    public function handle(BankAccount $bankAccount, BankAccountData $data): BankAccount
    {
        return DB::transaction(function () use ($bankAccount, $data): BankAccount {
            $account = match (true) {
                $bankAccount->exists => $bankAccount->account,
                $data->account_id !== null => Account::query()->findOrFail($data->account_id),
                default => $this->createAccount($data),
            };

            $account->update([
                'name' => ['ar' => $data->name, 'en' => $data->name],
                'currency_id' => $this->currencies->isBase($data->currency_id) ? null : $data->currency_id,
                'is_active' => $data->is_active,
            ]);

            $bankAccount->fill([...$data->toArray(), 'account_id' => $account->id])->save();

            return $bankAccount;
        });
    }

    protected function createAccount(BankAccountData $data): Account
    {
        $subtype = $data->type->accountSubtype();

        $parent = Account::query()
            ->where('is_group', true)
            ->where('subtype', $subtype)
            ->orderBy('code')
            ->first() ?? throw new AccountingException(__('Add a group account of type :type to the chart of accounts first.', ['type' => $subtype->label()]));

        return Account::query()->create([
            'parent_id' => $parent->id,
            'code' => $this->nextCode($parent),
            'name' => ['ar' => $data->name, 'en' => $data->name],
            'type' => AccountType::Asset,
            'subtype' => $subtype,
            'is_group' => false,
        ]);
    }

    /**
     * The parent's code followed by the next two-digit number, skipping codes
     * already taken anywhere in the chart.
     */
    protected function nextCode(Account $parent): string
    {
        $sequence = $parent->children()->count() + 1;

        do {
            $code = $parent->code.str_pad((string) $sequence++, 2, '0', STR_PAD_LEFT);
        } while (Account::query()->where('code', $code)->exists());

        return $code;
    }
}
