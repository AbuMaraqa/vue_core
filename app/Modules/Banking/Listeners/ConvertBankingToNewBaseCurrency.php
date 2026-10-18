<?php

namespace App\Modules\Banking\Listeners;

use App\Modules\Accounting\Events\BaseCurrencyChanged;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Banking\Models\BankAccount;
use Illuminate\Support\Facades\DB;

/**
 * Vouchers and cheques get their new rate. Bank and cash accounts kept in the
 * old base currency become foreign-currency accounts, so their balances stay
 * in that currency; that is only possible when every line on them is in it.
 */
class ConvertBankingToNewBaseCurrency
{
    public function handle(BaseCurrencyChanged $event): void
    {
        $conversion = $event->conversion;

        $conversion->convertExchangeRates('bank_vouchers');
        $conversion->convertExchangeRates('cheques');

        $bankAccounts = BankAccount::query()->where('currency_id', $conversion->from->id)->get();

        foreach ($bankAccounts as $bankAccount) {
            $hasOtherCurrencies = DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_lines.account_id', $bankAccount->account_id)
                ->where('journal_entries.currency_id', '!=', $conversion->from->id)
                ->exists();

            if ($hasOtherCurrencies) {
                throw new AccountingException(__('The account :account has transfers in other currencies, so it cannot be kept in :code. Change the base currency before such transfers, or keep :code as the base currency.', [
                    'account' => $bankAccount->name,
                    'code' => $conversion->from->code,
                ]));
            }

            Account::query()->whereKey($bankAccount->account_id)->update(['currency_id' => $conversion->from->id]);
        }
    }
}
