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

/**
 * The financial position on a date: assets = liabilities + equity. The
 * profit or loss not yet closed into retained earnings is shown as "current
 * earnings" in equity, so the sheet always balances.
 */
class BalanceSheet
{
    /**
     * @var array<string, array<string, list<AccountSubtype>>>
     */
    protected const array STRUCTURE = [
        'assets' => [
            'current_assets' => [
                AccountSubtype::Cash, AccountSubtype::Bank, AccountSubtype::Receivable, AccountSubtype::ChequesReceivable,
                AccountSubtype::Inventory, AccountSubtype::OtherCurrentAsset,
            ],
            'non_current_assets' => [AccountSubtype::FixedAsset, AccountSubtype::AccumulatedDepreciation, AccountSubtype::OtherAsset],
        ],
        'liabilities' => [
            'current_liabilities' => [AccountSubtype::Payable, AccountSubtype::ChequesPayable, AccountSubtype::TaxPayable, AccountSubtype::OtherCurrentLiability],
            'non_current_liabilities' => [AccountSubtype::LongTermLiability],
        ],
        'equity' => [
            'equity' => [AccountSubtype::Equity, AccountSubtype::RetainedEarnings],
        ],
    ];

    public function __construct(protected AccountBalances $balances) {}

    /**
     * @return array{sides: list<array<string, mixed>>, current_earnings: string, total_assets: string, total_liabilities_and_equity: string, balanced: bool}
     */
    public function generate(CarbonInterface $asOf): array
    {
        $balances = $this->balances->asOf($asOf);
        $accounts = Account::query()->where('is_group', false)->orderBy('code')->get();

        $currentEarnings = BigDecimal::zero();

        foreach ($accounts as $account) {
            if ($account->type->isIncomeStatement()) {
                $currentEarnings = $currentEarnings->minus($balances[$account->id] ?? '0');
            }
        }

        $sides = [];
        $sideTotals = [];

        foreach (self::STRUCTURE as $side => $groups) {
            $sideTotal = BigDecimal::zero();
            $groupRows = [];

            foreach ($groups as $group => $subtypes) {
                $rows = [];
                $groupTotal = BigDecimal::zero();

                foreach ($accounts->filter(fn (Account $account): bool => in_array($account->subtype, $subtypes, true)) as $account) {
                    $balance = Decimal::of($balances[$account->id] ?? '0');
                    $amount = $account->type === AccountType::Asset ? $balance : $balance->negated();

                    if ($amount->isZero()) {
                        continue;
                    }

                    $groupTotal = $groupTotal->plus($amount);
                    $rows[] = ['account_id' => $account->id, 'code' => $account->code, 'name' => $account->translate('name'), 'amount' => Decimal::round($amount)];
                }

                if ($group === 'equity' && ! $currentEarnings->isZero()) {
                    $groupTotal = $groupTotal->plus($currentEarnings);
                    $rows[] = ['account_id' => null, 'code' => '', 'name' => __('Current earnings (not yet closed)'), 'amount' => Decimal::round($currentEarnings)];
                }

                $sideTotal = $sideTotal->plus($groupTotal);
                $groupRows[] = ['key' => $group, 'title' => __(str($group)->headline()->value()), 'accounts' => $rows, 'total' => Decimal::round($groupTotal)];
            }

            $sideTotals[$side] = $sideTotal;
            $sides[] = ['key' => $side, 'title' => __(str($side)->headline()->value()), 'groups' => $groupRows, 'total' => Decimal::round($sideTotal)];
        }

        $assets = $sideTotals['assets'];
        $liabilitiesAndEquity = $sideTotals['liabilities']->plus($sideTotals['equity']);

        return [
            'sides' => $sides,
            'current_earnings' => Decimal::round($currentEarnings),
            'total_assets' => Decimal::round($assets),
            'total_liabilities_and_equity' => Decimal::round($liabilitiesAndEquity),
            'balanced' => $assets->isEqualTo($liabilitiesAndEquity),
        ];
    }

    public function export(CarbonInterface $asOf): Report
    {
        $report = $this->generate($asOf);
        $rows = [];

        foreach ($report['sides'] as $side) {
            $rows[] = [$side['title'], ''];

            foreach ($side['groups'] as $group) {
                $rows[] = ["  {$group['title']}", ''];

                foreach ($group['accounts'] as $account) {
                    $rows[] = ["     {$account['code']} {$account['name']}", $account['amount']];
                }

                $rows[] = ['  '.__('Total').' '.$group['title'], $group['total']];
            }

            $rows[] = [__('Total').' '.$side['title'], $side['total']];
        }

        $rows[] = [__('Total liabilities and equity'), $report['total_liabilities_and_equity']];

        return new Report('Balance Sheet', ['Item', 'Amount'], $rows, [__('As of :date', ['date' => $asOf->toDateString()])]);
    }
}
