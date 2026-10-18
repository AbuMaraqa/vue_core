<?php

namespace App\Modules\Accounting\Reports;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\BudgetLine;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Foundation\Exports\Report;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Planned against actual amounts of a budget, up to a month of its fiscal
 * year. For revenue a positive variance is good news; for expenses a positive
 * variance means overspending.
 */
class BudgetVsActual
{
    public function __construct(protected AccountBalances $balances) {}

    /**
     * @param  int  $throughMonth  1–12: the last month of the fiscal year included.
     * @return array{rows: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function generate(Budget $budget, int $throughMonth = 12): array
    {
        $budget->loadMissing(['fiscalYear', 'lines.account']);
        $year = $budget->fiscalYear;
        $to = $year->start_date->copy()->addMonthsNoOverflow($throughMonth)->subDay()->min($year->end_date);

        $lines = $budget->lines->filter(fn (BudgetLine $line): bool => $line->month <= $throughMonth)->groupBy('account_id');
        $movements = $this->balances->movements($year->start_date, $to, $lines->keys()->all(), $budget->cost_center_id, excludeClosing: true);

        $rows = [];
        $totals = ['budget' => BigDecimal::zero(), 'actual' => BigDecimal::zero()];

        foreach ($lines as $accountId => $accountLines) {
            $account = $accountLines->first()->account;
            $planned = Decimal::sum($accountLines->pluck('amount'));
            $balance = Decimal::of($movements[$accountId]['balance'] ?? '0');
            $actual = $account->type === AccountType::Revenue ? $balance->negated() : $balance;
            $variance = $actual->minus($planned);

            $rows[] = [
                'account_id' => $accountId,
                'account' => $account->label(),
                'type' => $account->type->value,
                'budget' => Decimal::round($planned),
                'actual' => Decimal::round($actual),
                'variance' => Decimal::round($variance),
                'percent' => $planned->isZero() ? null : (string) $actual->multipliedBy(100)->dividedBy($planned, 1, RoundingMode::HalfUp),
            ];

            $totals['budget'] = $totals['budget']->plus($planned);
            $totals['actual'] = $totals['actual']->plus($actual);
        }

        usort($rows, fn (array $first, array $second): int => strcmp($first['account'], $second['account']));

        return [
            'rows' => $rows,
            'totals' => array_map(fn (BigDecimal $value): string => Decimal::round($value), $totals),
        ];
    }

    public function export(Budget $budget, int $throughMonth = 12): Report
    {
        $report = $this->generate($budget, $throughMonth);

        return new Report(
            'Budget vs Actual',
            ['Account', 'Budget', 'Actual', 'Variance', '%'],
            array_map(fn (array $row): array => [$row['account'], $row['budget'], $row['actual'], $row['variance'], $row['percent']], $report['rows']),
            [$budget->name],
        );
    }
}
