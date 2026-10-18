<?php

namespace App\Modules\Accounting\Reports;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Foundation\Exports\Report;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;

/**
 * Opening balance, movements of the period and closing balance of every
 * account. The period leaves out year-end closing entries (a pre-closing
 * trial balance), so the revenue and expenses of a closed year still show.
 * Total debits always equal total credits.
 */
class TrialBalance
{
    public function __construct(protected AccountBalances $balances) {}

    /**
     * @return array{rows: list<array<string, mixed>>, totals: array<string, string>, balanced: bool}
     */
    public function generate(CarbonInterface $from, CarbonInterface $to, ?int $costCenterId = null, bool $includeZero = false): array
    {
        $opening = $this->balances->movements(null, $from->copy()->subDay(), null, $costCenterId);
        $period = $this->balances->movements($from, $to, null, $costCenterId, excludeClosing: true);

        $rows = [];
        $totals = array_fill_keys(['opening_debit', 'opening_credit', 'debit', 'credit', 'closing_debit', 'closing_credit'], BigDecimal::zero());

        foreach (Account::query()->where('is_group', false)->orderBy('code')->get() as $account) {
            $openingBalance = Decimal::of($opening[$account->id]['balance'] ?? '0');
            $debit = Decimal::of($period[$account->id]['debit'] ?? '0');
            $credit = Decimal::of($period[$account->id]['credit'] ?? '0');
            $closing = $openingBalance->plus($debit)->minus($credit);

            if (! $includeZero && $openingBalance->isZero() && $debit->isZero() && $credit->isZero()) {
                continue;
            }

            $row = [
                'opening_debit' => $openingBalance->isPositive() ? $openingBalance : BigDecimal::zero(),
                'opening_credit' => $openingBalance->isNegative() ? $openingBalance->abs() : BigDecimal::zero(),
                'debit' => $debit,
                'credit' => $credit,
                'closing_debit' => $closing->isPositive() ? $closing : BigDecimal::zero(),
                'closing_credit' => $closing->isNegative() ? $closing->abs() : BigDecimal::zero(),
            ];

            foreach ($row as $key => $value) {
                $totals[$key] = $totals[$key]->plus($value);
            }

            $rows[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->translate('name'),
                'type' => $account->type->value,
                ...array_map(fn (BigDecimal $value): string => Decimal::round($value), $row),
            ];
        }

        $totals = array_map(fn (BigDecimal $value): string => Decimal::round($value), $totals);

        return [
            'rows' => $rows,
            'totals' => $totals,
            'balanced' => $totals['closing_debit'] === $totals['closing_credit'],
        ];
    }

    public function export(CarbonInterface $from, CarbonInterface $to, ?int $costCenterId = null, bool $includeZero = false): Report
    {
        $report = $this->generate($from, $to, $costCenterId, $includeZero);

        $rows = array_map(fn (array $row): array => [
            $row['code'], $row['name'],
            $row['opening_debit'], $row['opening_credit'],
            $row['debit'], $row['credit'],
            $row['closing_debit'], $row['closing_credit'],
        ], $report['rows']);

        $rows[] = ['', __('Total'), ...array_values($report['totals'])];

        return new Report(
            'Trial Balance',
            ['Code', 'Account', 'Opening debit', 'Opening credit', 'Debit', 'Credit', 'Closing debit', 'Closing credit'],
            $rows,
            [__(':from to :to', ['from' => $from->toDateString(), 'to' => $to->toDateString()])],
        );
    }
}
