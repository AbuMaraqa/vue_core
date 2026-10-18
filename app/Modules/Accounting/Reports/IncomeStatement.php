<?php

namespace App\Modules\Accounting\Reports;

use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Foundation\Exports\Report;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Profit and loss of a period: revenue, cost of sales, gross profit,
 * operating expenses, operating profit, other items and net profit, with an
 * optional comparison period. Amounts are shown as positive numbers on their
 * natural side.
 */
class IncomeStatement
{
    /**
     * @var array<string, list<AccountSubtype>>
     */
    protected const array SECTIONS = [
        'revenue' => [AccountSubtype::OperatingRevenue],
        'cost_of_sales' => [AccountSubtype::CostOfSales],
        'operating_expenses' => [AccountSubtype::OperatingExpense],
        'other_revenue' => [AccountSubtype::OtherRevenue],
        'other_expenses' => [AccountSubtype::OtherExpense],
    ];

    public function __construct(protected AccountBalances $balances) {}

    /**
     * @return array{sections: list<array<string, mixed>>, gross_profit: string, operating_profit: string, net_profit: string, previous: array<string, string>|null}
     */
    public function generate(CarbonInterface $from, CarbonInterface $to, ?int $costCenterId = null, ?CarbonInterface $compareFrom = null, ?CarbonInterface $compareTo = null): array
    {
        $accounts = Account::query()
            ->whereIn('type', [AccountType::Revenue, AccountType::Expense])
            ->where('is_group', false)
            ->orderBy('code')
            ->get();

        $current = $this->amounts($accounts, $from, $to, $costCenterId);
        $previous = $compareFrom && $compareTo ? $this->amounts($accounts, $compareFrom, $compareTo, $costCenterId) : null;

        $sections = [];
        $totals = [];
        $previousTotals = [];

        foreach (self::SECTIONS as $key => $subtypes) {
            $rows = [];
            $total = BigDecimal::zero();
            $previousTotal = BigDecimal::zero();

            foreach ($accounts->filter(fn (Account $account): bool => in_array($account->subtype, $subtypes, true)) as $account) {
                $amount = $current[$account->id] ?? BigDecimal::zero();
                $before = $previous[$account->id] ?? BigDecimal::zero();

                if ($amount->isZero() && $before->isZero()) {
                    continue;
                }

                $total = $total->plus($amount);
                $previousTotal = $previousTotal->plus($before);

                $rows[] = [
                    'account_id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->translate('name'),
                    'amount' => Decimal::round($amount),
                    'previous' => $previous !== null ? Decimal::round($before) : null,
                ];
            }

            $totals[$key] = $total;
            $previousTotals[$key] = $previousTotal;

            $sections[] = [
                'key' => $key,
                'title' => __(str($key)->headline()->value()),
                'accounts' => $rows,
                'total' => Decimal::round($total),
                'previous_total' => $previous !== null ? Decimal::round($previousTotal) : null,
            ];
        }

        $results = $this->results($totals);

        return [
            'sections' => $sections,
            ...array_map(fn (BigDecimal $value): string => Decimal::round($value), $results),
            'previous' => $previous !== null
                ? array_map(fn (BigDecimal $value): string => Decimal::round($value), $this->results($previousTotals))
                : null,
        ];
    }

    public function export(CarbonInterface $from, CarbonInterface $to, ?int $costCenterId = null): Report
    {
        $report = $this->generate($from, $to, $costCenterId);
        $rows = [];
        $subtotals = ['cost_of_sales' => 'gross_profit', 'operating_expenses' => 'operating_profit', 'other_expenses' => 'net_profit'];

        foreach ($report['sections'] as $section) {
            $rows[] = [$section['title'], ''];

            foreach ($section['accounts'] as $account) {
                $rows[] = ["   {$account['code']} {$account['name']}", $account['amount']];
            }

            $rows[] = [__('Total').' '.$section['title'], $section['total']];

            if (isset($subtotals[$section['key']])) {
                $key = $subtotals[$section['key']];
                $rows[] = [__(str($key)->headline()->value()), $report[$key]];
            }
        }

        return new Report('Income Statement', ['Item', 'Amount'], $rows, [
            __(':from to :to', ['from' => $from->toDateString(), 'to' => $to->toDateString()]),
        ]);
    }

    /**
     * Revenue accounts have credit balances, expenses debit balances; both are
     * turned into positive amounts.
     *
     * @param  Collection<int, Account>  $accounts
     * @return array<int, BigDecimal>
     */
    protected function amounts($accounts, CarbonInterface $from, CarbonInterface $to, ?int $costCenterId): array
    {
        $movements = $this->balances->movements($from, $to, $accounts->pluck('id')->all(), $costCenterId, excludeClosing: true);
        $amounts = [];

        foreach ($accounts as $account) {
            $balance = Decimal::of($movements[$account->id]['balance'] ?? '0');
            $amounts[$account->id] = $account->type === AccountType::Revenue ? $balance->negated() : $balance;
        }

        return $amounts;
    }

    /**
     * @param  array<string, BigDecimal>  $totals
     * @return array{gross_profit: BigDecimal, operating_profit: BigDecimal, net_profit: BigDecimal}
     */
    protected function results(array $totals): array
    {
        $gross = $totals['revenue']->minus($totals['cost_of_sales']);
        $operating = $gross->minus($totals['operating_expenses']);

        return [
            'gross_profit' => $gross,
            'operating_profit' => $operating,
            'net_profit' => $operating->plus($totals['other_revenue'])->minus($totals['other_expenses']),
        ];
    }
}
