<?php

namespace App\Modules\Contacts\Reports;

use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Support\ContactBalances;
use App\Modules\Foundation\Exports\Report;
use Carbon\CarbonInterface;

/**
 * The statement of account sent to a customer or vendor: the opening
 * balance, every posted movement of the period with the running balance, and
 * the closing balance. Positive balances are owed to us.
 */
class ContactStatement
{
    /**
     * Very long statements are cut, with a notice, to keep the page responsive.
     */
    public const int MAX_LINES = 5000;

    public function __construct(
        protected ContactBalances $balances,
        protected Currencies $currencies,
    ) {}

    /**
     * @return array{opening: string, closing: string, total_debit: string, total_credit: string, lines: list<array<string, mixed>>, truncated: bool}
     */
    public function generate(Contact $contact, CarbonInterface $from, CarbonInterface $to): array
    {
        $opening = $this->balances->balance($contact, $from->copy()->subDay());

        $lines = $this->balances->lines($from, $to)
            ->where('journal_lines.partner_id', $contact->id)
            ->join('currencies', 'currencies.id', '=', 'journal_entries.currency_id')
            ->orderBy('journal_entries.date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.sort')
            ->select([
                'journal_entries.id as entry_id', 'journal_entries.date', 'journal_entries.number', 'journal_entries.reference',
                'journal_entries.description as entry_description', 'journal_entries.currency_id', 'journal_lines.description',
                'journal_lines.debit', 'journal_lines.credit', 'journal_lines.base_debit', 'journal_lines.base_credit',
                'currencies.code as currency', 'currencies.decimal_places',
            ])
            ->limit(self::MAX_LINES + 1)
            ->get();

        $baseCurrencyId = $this->currencies->base()->id;
        $running = Decimal::of($opening);
        $debits = Decimal::of(0);
        $credits = Decimal::of(0);
        $rows = [];

        foreach ($lines->take(self::MAX_LINES) as $line) {
            $running = $running->plus((string) $line->base_debit)->minus((string) $line->base_credit);
            $debits = $debits->plus((string) $line->base_debit);
            $credits = $credits->plus((string) $line->base_credit);
            $isForeign = (int) $line->currency_id !== $baseCurrencyId;

            $rows[] = [
                'entry_id' => (int) $line->entry_id,
                'date' => substr((string) $line->date, 0, 10),
                'number' => $line->number,
                'description' => $line->description ?? $line->entry_description,
                'reference' => $line->reference,
                'debit' => Decimal::round((string) $line->base_debit),
                'credit' => Decimal::round((string) $line->base_credit),
                'balance' => Decimal::round($running),
                'foreign' => $isForeign ? [
                    'currency' => $line->currency,
                    'decimal_places' => (int) $line->decimal_places,
                    'amount' => Decimal::round(Decimal::of((string) $line->debit)->minus((string) $line->credit)),
                ] : null,
            ];
        }

        return [
            'opening' => Decimal::round($opening),
            'closing' => Decimal::round($running),
            'total_debit' => Decimal::round($debits),
            'total_credit' => Decimal::round($credits),
            'lines' => $rows,
            'truncated' => $lines->count() > self::MAX_LINES,
        ];
    }

    public function export(Contact $contact, CarbonInterface $from, CarbonInterface $to): Report
    {
        $statement = $this->generate($contact, $from, $to);

        $rows = [['', '', __('Opening balance'), '', '', $statement['opening']]];

        foreach ($statement['lines'] as $line) {
            $rows[] = [$line['date'], $line['number'], $line['description'], $line['debit'], $line['credit'], $line['balance']];
        }

        $rows[] = ['', '', __('Closing balance'), $statement['total_debit'], $statement['total_credit'], $statement['closing']];

        return new Report(
            'Statement of Account',
            ['Date', 'Number', 'Description', 'Debit', 'Credit', 'Balance'],
            $rows,
            [$contact->label(), __(':from to :to', ['from' => $from->toDateString(), 'to' => $to->toDateString()])],
        );
    }
}
