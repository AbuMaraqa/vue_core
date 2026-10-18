<?php

namespace App\Modules\Accounting\Reports;

use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Foundation\Exports\Report;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Where the cash came from and went to, by the indirect method: net profit,
 * plus depreciation, plus or minus the changes in working capital (operating
 * activities), then investing and financing activities. The net change
 * always equals the change in the cash and bank balances.
 *
 * Every balance sheet account that is not cash moves cash by the opposite of
 * its own movement: a receivable that grows is cash not yet collected.
 */
class CashFlowStatement
{
    public function __construct(
        protected AccountBalances $balances,
        protected IncomeStatement $incomeStatement,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(CarbonInterface $from, CarbonInterface $to): array
    {
        $accounts = Account::query()->where('is_group', false)->orderBy('code')->get()->keyBy('id');
        $movements = $this->balances->movements($from, $to, excludeClosing: true);
        $netProfit = Decimal::of($this->incomeStatement->generate($from, $to)['net_profit']);

        $sections = [
            'operating' => ['items' => [['label' => __('Net profit'), 'amount' => $netProfit]], 'total' => $netProfit],
            'investing' => ['items' => [], 'total' => BigDecimal::zero()],
            'financing' => ['items' => [], 'total' => BigDecimal::zero()],
        ];

        $cashChange = BigDecimal::zero();

        foreach ($movements as $accountId => $movement) {
            $account = $accounts->get($accountId);

            if ($account === null || $account->type->isIncomeStatement()) {
                continue;
            }

            $balance = Decimal::of($movement['balance']);

            if ($account->subtype->isCashEquivalent()) {
                $cashChange = $cashChange->plus($balance);

                continue;
            }

            $effect = $balance->negated();

            if ($effect->isZero()) {
                continue;
            }

            $section = match (true) {
                $account->subtype->isWorkingCapital(), $account->subtype === AccountSubtype::AccumulatedDepreciation => 'operating',
                in_array($account->subtype, [AccountSubtype::FixedAsset, AccountSubtype::OtherAsset], true) => 'investing',
                default => 'financing',
            };

            $sections[$section]['items'][] = ['label' => $account->label(), 'amount' => $effect];
            $sections[$section]['total'] = $sections[$section]['total']->plus($effect);
        }

        $netChange = $sections['operating']['total']->plus($sections['investing']['total'])->plus($sections['financing']['total']);
        $openingCash = $this->cashBalance($accounts, $from->copy()->subDay());

        return [
            'sections' => array_map(fn (string $key, array $section): array => [
                'key' => $key,
                'title' => __(str($key)->headline()->append(' Activities')->value()),
                'items' => array_map(fn (array $item): array => [...$item, 'amount' => Decimal::round($item['amount'])], $section['items']),
                'total' => Decimal::round($section['total']),
            ], array_keys($sections), $sections),
            'net_change' => Decimal::round($netChange),
            'opening_cash' => Decimal::round($openingCash),
            'closing_cash' => Decimal::round($openingCash->plus($netChange)),
            'reconciled' => $netChange->isEqualTo($cashChange),
        ];
    }

    public function export(CarbonInterface $from, CarbonInterface $to): Report
    {
        $report = $this->generate($from, $to);
        $rows = [];

        foreach ($report['sections'] as $section) {
            $rows[] = [$section['title'], ''];

            foreach ($section['items'] as $item) {
                $rows[] = ["   {$item['label']}", $item['amount']];
            }

            $rows[] = [__('Net cash from').' '.$section['title'], $section['total']];
        }

        $rows[] = [__('Net change in cash'), $report['net_change']];
        $rows[] = [__('Cash at the beginning of the period'), $report['opening_cash']];
        $rows[] = [__('Cash at the end of the period'), $report['closing_cash']];

        return new Report('Cash Flow Statement', ['Item', 'Amount'], $rows, [
            __(':from to :to', ['from' => $from->toDateString(), 'to' => $to->toDateString()]),
        ]);
    }

    /**
     * @param  Collection<int, Account>  $accounts
     */
    protected function cashBalance($accounts, CarbonInterface $date): BigDecimal
    {
        $cashAccounts = $accounts->filter(fn (Account $account): bool => $account->subtype->isCashEquivalent())->keys()->all();

        return Decimal::sum(array_column($this->balances->movements(null, $date, $cashAccounts), 'balance'));
    }
}
