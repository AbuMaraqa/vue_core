<?php

namespace App\Modules\Accounting\Reports;

use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The key figures of the accounting dashboard: cash, receivables, payables,
 * this month's revenue and expenses, the year's profit, a 12-month trend and
 * the largest expenses.
 */
class FinancialOverview
{
    public function __construct(
        protected AccountBalances $balances,
        protected IncomeStatement $incomeStatement,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(?CarbonInterface $today = null): array
    {
        $today ??= now();
        $accounts = Account::query()->where('is_group', false)->get();
        $year = FiscalYear::query()->containing($today)->first();
        $yearStart = $year->start_date ?? $today->copy()->startOfYear();

        return [
            ...$this->figures($accounts, $today),
            'year_profit' => $this->incomeStatement->generate($yearStart, $today)['net_profit'],
            'trend' => $this->trend($accounts, $today),
            'top_expenses' => $this->topExpenses($accounts, $yearStart, $today),
        ];
    }

    /**
     * Only the key figures, without the trend and the year totals.
     *
     * @return array{cash: string, receivables: string, cheques_receivable: string, payables: string, month_revenue: string, month_expenses: string}
     */
    public function summary(?CarbonInterface $today = null): array
    {
        return $this->figures(Account::query()->where('is_group', false)->get(), $today ?? now());
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return array{cash: string, receivables: string, cheques_receivable: string, payables: string, month_revenue: string, month_expenses: string}
     */
    protected function figures(Collection $accounts, CarbonInterface $today): array
    {
        $balances = $this->balances->asOf($today);

        $sum = fn (array $subtypes, bool $creditNormal = false): string => Decimal::round(
            Decimal::sum($accounts
                ->filter(fn (Account $account): bool => in_array($account->subtype, $subtypes, true))
                ->map(fn (Account $account): BigDecimal => $creditNormal
                    ? Decimal::of($balances[$account->id] ?? '0')->negated()
                    : Decimal::of($balances[$account->id] ?? '0'))),
        );

        $month = $this->incomeStatement->generate($today->copy()->startOfMonth(), $today);

        return [
            'cash' => $sum([AccountSubtype::Cash, AccountSubtype::Bank]),
            'receivables' => $sum([AccountSubtype::Receivable]),
            'cheques_receivable' => $sum([AccountSubtype::ChequesReceivable]),
            'payables' => $sum([AccountSubtype::Payable, AccountSubtype::ChequesPayable], creditNormal: true),
            'month_revenue' => Decimal::round(Decimal::of($this->sectionTotal($month, 'revenue'))->plus($this->sectionTotal($month, 'other_revenue'))),
            'month_expenses' => Decimal::round(Decimal::sum([
                $this->sectionTotal($month, 'cost_of_sales'),
                $this->sectionTotal($month, 'operating_expenses'),
                $this->sectionTotal($month, 'other_expenses'),
            ])),
        ];
    }

    /**
     * Revenue and expenses of each of the last 12 months.
     *
     * @param  Collection<int, Account>  $accounts
     * @return list<array{month: string, revenue: string, expenses: string}>
     */
    protected function trend(Collection $accounts, CarbonInterface $today): array
    {
        $start = $today->copy()->startOfMonth()->subMonths(11);
        $incomeAccounts = $accounts->filter(fn (Account $account): bool => $account->type->isIncomeStatement())->keyBy('id');
        $monthly = $this->balances->monthly($start, $today, $incomeAccounts->keys()->all());

        $trend = [];

        for ($month = $start; $month->lessThanOrEqualTo($today); $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $revenue = BigDecimal::zero();
            $expenses = BigDecimal::zero();

            foreach ($monthly as $accountId => $months) {
                $balance = Decimal::of($months[$key] ?? '0');

                if ($incomeAccounts->get($accountId)?->type === AccountType::Revenue) {
                    $revenue = $revenue->minus($balance);
                } else {
                    $expenses = $expenses->plus($balance);
                }
            }

            $trend[] = ['month' => $key, 'revenue' => Decimal::round($revenue), 'expenses' => Decimal::round($expenses)];
        }

        return $trend;
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return list<array{account: string, amount: string}>
     */
    protected function topExpenses(Collection $accounts, CarbonInterface $from, CarbonInterface $to): array
    {
        $expenseAccounts = $accounts->filter(fn (Account $account): bool => $account->type === AccountType::Expense)->keyBy('id');
        $movements = $this->balances->movements($from, $to, $expenseAccounts->keys()->all(), excludeClosing: true);

        return array_values(collect($movements)
            ->map(fn (array $movement, int $accountId): array => [
                'account' => $expenseAccounts->get($accountId)?->translate('name') ?? '',
                'amount' => $movement['balance'],
            ])
            ->filter(fn (array $row): bool => Decimal::isPositive($row['amount']))
            ->sort(fn (array $first, array $second): int => Decimal::of($second['amount'])->compareTo(Decimal::of($first['amount'])))
            ->take(5)
            ->all());
    }

    /**
     * @param  array{sections: list<array<string, mixed>>}  $statement
     */
    protected function sectionTotal(array $statement, string $key): string
    {
        return (string) (collect($statement['sections'])->firstWhere('key', $key)['total'] ?? '0');
    }
}
