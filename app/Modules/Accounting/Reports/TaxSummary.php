<?php

namespace App\Modules\Accounting\Reports;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Foundation\Exports\Report;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;

/**
 * The tax return of a period: tax collected on sales (output tax) minus tax
 * paid on purchases (input tax) is the amount owed to the tax authority, or
 * to be recovered when negative. Read from the tax accounts of the taxes.
 */
class TaxSummary
{
    public function __construct(protected AccountBalances $balances) {}

    /**
     * @return array{output: list<array<string, string>>, input: list<array<string, string>>, total_output: string, total_input: string, net: string}
     */
    public function generate(CarbonInterface $from, CarbonInterface $to): array
    {
        $outputIds = Tax::query()->whereNotNull('sales_account_id')->distinct()->pluck('sales_account_id')->all();
        $inputIds = Tax::query()->whereNotNull('purchase_account_id')->distinct()->pluck('purchase_account_id')->all();

        $movements = $this->balances->movements($from, $to, array_values(array_unique([...$outputIds, ...$inputIds])));
        $accounts = Account::query()->whereIn('id', [...$outputIds, ...$inputIds])->get()->keyBy('id');

        $side = function (array $ids, bool $isOutput) use ($movements, $accounts): array {
            $rows = [];
            $total = BigDecimal::zero();

            foreach ($ids as $id) {
                $movement = $movements[$id] ?? ['debit' => '0', 'credit' => '0'];
                // Output tax is credited when charged; input tax is debited when paid.
                $amount = $isOutput
                    ? Decimal::of($movement['credit'])->minus($movement['debit'])
                    : Decimal::of($movement['debit'])->minus($movement['credit']);

                $total = $total->plus($amount);
                $rows[] = ['account' => $accounts->get($id)?->label() ?? '', 'amount' => Decimal::round($amount)];
            }

            return [$rows, $total];
        };

        [$output, $totalOutput] = $side($outputIds, true);
        [$input, $totalInput] = $side($inputIds, false);

        return [
            'output' => $output,
            'input' => $input,
            'total_output' => Decimal::round($totalOutput),
            'total_input' => Decimal::round($totalInput),
            'net' => Decimal::round($totalOutput->minus($totalInput)),
        ];
    }

    public function export(CarbonInterface $from, CarbonInterface $to): Report
    {
        $report = $this->generate($from, $to);

        $rows = [[__('Output tax (sales)'), '']];

        foreach ($report['output'] as $row) {
            $rows[] = ["   {$row['account']}", $row['amount']];
        }

        $rows[] = [__('Input tax (purchases)'), ''];

        foreach ($report['input'] as $row) {
            $rows[] = ["   {$row['account']}", $row['amount']];
        }

        $rows[] = [__('Net tax payable'), $report['net']];

        return new Report('Tax Report', ['Item', 'Amount'], $rows, [
            __(':from to :to', ['from' => $from->toDateString(), 'to' => $to->toDateString()]),
        ]);
    }
}
