<?php

namespace App\Modules\Banking\Reports;

use App\Modules\Accounting\Support\Decimal;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Support\BankBalances;
use App\Modules\Foundation\Exports\Report;
use Carbon\CarbonInterface;

/**
 * The register of a bank or cash account: money in and out in the account's
 * currency with the running balance, and whether each line is reconciled.
 */
class BankAccountRegister
{
    /**
     * Very long registers are cut, with a notice, to keep the page responsive.
     */
    public const int MAX_LINES = 5000;

    public function __construct(protected BankBalances $balances) {}

    /**
     * @return array{opening: string, closing: string, total_in: string, total_out: string, lines: list<array<string, mixed>>, truncated: bool}
     */
    public function generate(BankAccount $bankAccount, CarbonInterface $from, CarbonInterface $to): array
    {
        $opening = $this->balances->balance($bankAccount, $from->copy()->subDay());

        $lines = $this->balances->lines($bankAccount, $from, $to)
            ->orderBy('journal_entries.date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.sort')
            ->limit(self::MAX_LINES + 1)
            ->get();

        $running = Decimal::of($opening);
        $totalIn = Decimal::of(0);
        $totalOut = Decimal::of(0);
        $rows = [];

        foreach ($lines->take(self::MAX_LINES) as $line) {
            $amount = Decimal::of((string) $line->amount);
            $running = $running->plus($amount);

            if ($amount->isPositive()) {
                $totalIn = $totalIn->plus($amount);
            } else {
                $totalOut = $totalOut->plus($amount->abs());
            }

            $rows[] = [
                'id' => (int) $line->id,
                'entry_id' => (int) $line->entry_id,
                'date' => substr((string) $line->date, 0, 10),
                'number' => $line->number,
                'reference' => $line->reference,
                'description' => $line->description ?? $line->entry_description,
                'in' => $amount->isPositive() ? Decimal::round($amount) : null,
                'out' => $amount->isNegative() ? Decimal::round($amount->abs()) : null,
                'balance' => Decimal::round($running),
                'reconciled' => $line->reconciliation_id !== null,
            ];
        }

        return [
            'opening' => Decimal::round($opening),
            'closing' => Decimal::round($running),
            'total_in' => Decimal::round($totalIn),
            'total_out' => Decimal::round($totalOut),
            'lines' => $rows,
            'truncated' => $lines->count() > self::MAX_LINES,
        ];
    }

    public function export(BankAccount $bankAccount, CarbonInterface $from, CarbonInterface $to): Report
    {
        $register = $this->generate($bankAccount, $from, $to);

        $rows = [['', '', __('Opening balance'), '', '', $register['opening'], '']];

        foreach ($register['lines'] as $line) {
            $rows[] = [$line['date'], $line['number'], $line['description'], $line['in'], $line['out'], $line['balance'], $line['reconciled'] ? __('Yes') : ''];
        }

        $rows[] = ['', '', __('Closing balance'), $register['total_in'], $register['total_out'], $register['closing'], ''];

        return new Report(
            'Bank Account Register',
            ['Date', 'Number', 'Description', 'Money in', 'Money out', 'Balance', 'Reconciled'],
            $rows,
            [$bankAccount->label(), __(':from to :to', ['from' => $from->toDateString(), 'to' => $to->toDateString()])],
        );
    }
}
