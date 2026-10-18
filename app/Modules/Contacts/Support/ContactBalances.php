<?php

namespace App\Modules\Contacts\Support;

use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Contacts\Models\Contact;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;

/**
 * What contacts owe us and what we owe them, in the base currency, read from
 * the posted journal lines that name the contact on a receivable or payable
 * account. Every document (invoice, payment, cheque, opening balance) writes
 * such lines, so the balance never drifts from the books.
 */
class ContactBalances
{
    public function __construct(protected AccountBalances $balances) {}

    /**
     * The balances of each contact at the end of the day, keyed by contact id.
     * `receivable` is what they owe us, `payable` what we owe them, and
     * `balance` the net: positive when they owe us.
     *
     * @param  array<int>  $contactIds
     * @return array<int, array{receivable: string, payable: string, balance: string}>
     */
    public function of(array $contactIds, CarbonInterface|string|null $asOf = null): array
    {
        if ($contactIds === []) {
            return [];
        }

        $rows = $this->lines(null, $asOf)
            ->whereIn('journal_lines.partner_id', $contactIds)
            ->groupBy('journal_lines.partner_id', 'accounts.subtype')
            ->select('journal_lines.partner_id', 'accounts.subtype')
            ->selectRaw('SUM(journal_lines.base_debit) - SUM(journal_lines.base_credit) as balance')
            ->get();

        $balances = [];

        foreach ($rows as $row) {
            $contactId = (int) $row->partner_id;
            $balances[$contactId] ??= ['receivable' => '0', 'payable' => '0', 'balance' => '0'];
            $amount = Decimal::of((string) $row->balance);

            if ($row->subtype === AccountSubtype::Payable->value) {
                $balances[$contactId]['payable'] = Decimal::round(Decimal::of($balances[$contactId]['payable'])->minus($amount));
            } else {
                $balances[$contactId]['receivable'] = Decimal::round(Decimal::of($balances[$contactId]['receivable'])->plus($amount));
            }

            $balances[$contactId]['balance'] = Decimal::round(Decimal::of($balances[$contactId]['balance'])->plus($amount));
        }

        return $balances;
    }

    /**
     * The net balance of one contact: positive when they owe us.
     */
    public function balance(Contact $contact, CarbonInterface|string|null $asOf = null): string
    {
        return $this->of([$contact->id], $asOf)[$contact->id]['balance'] ?? '0';
    }

    /**
     * Posted contact lines on receivable and payable accounts between the dates.
     */
    public function lines(CarbonInterface|string|null $from = null, CarbonInterface|string|null $to = null): Builder
    {
        return $this->balances->lines($from, $to)
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_lines.partner_type', (new Contact)->getMorphClass())
            ->whereIn('accounts.subtype', [AccountSubtype::Receivable->value, AccountSubtype::Payable->value]);
    }
}
