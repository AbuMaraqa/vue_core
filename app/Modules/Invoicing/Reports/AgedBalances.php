<?php

namespace App\Modules\Invoicing\Reports;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Foundation\Exports\Report;
use App\Modules\Foundation\Support\Filters\DateFilter;
use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Invoicing\Models\Invoice;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Aged receivables (sales invoices) or payables (purchase bills) on a date:
 * what each contact still owes, or is owed, by how late it is. Each
 * invoice counts what was due on that date (settlements after it are left
 * out), in the base currency at the invoice's rate.
 */
class AgedBalances
{
    /**
     * The age buckets, by days past the due date.
     */
    public const array BUCKETS = ['current' => [null, 0], '1_30' => [1, 30], '31_60' => [31, 60], '61_90' => [61, 90], 'over_90' => [91, null]];

    /**
     * @return array{contacts: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function generate(InvoiceType $type, CarbonInterface $asOf): array
    {
        $invoices = Invoice::query()
            ->with(['contact', 'allocations' => fn (Builder $query) => DateFilter::onOrBefore($query, 'date', $asOf)])
            ->where('type', $type)
            ->where('status', DocumentStatus::Posted)
            ->tap(fn (Builder $query) => DateFilter::onOrBefore($query, 'date', $asOf))
            ->get();

        $empty = fn (): array => array_fill_keys([...array_keys(self::BUCKETS), 'total'], BigDecimal::zero());
        $names = [];
        $counts = [];
        $amounts = [];
        $totals = $empty();

        foreach ($invoices as $invoice) {
            $due = Decimal::of($invoice->total)->minus(Decimal::sum($invoice->allocations->pluck('amount')));

            if (! $due->isPositive()) {
                continue;
            }

            $base = Decimal::of(Decimal::multiply($due, (string) $invoice->exchange_rate));
            $bucket = $this->bucket((int) $invoice->due_date->diffInDays($asOf, false));
            $contactId = $invoice->contact_id;

            $names[$contactId] = $invoice->contact->name;
            $counts[$contactId] = ($counts[$contactId] ?? 0) + 1;
            $amounts[$contactId] ??= $empty();
            $amounts[$contactId][$bucket] = $amounts[$contactId][$bucket]->plus($base);
            $amounts[$contactId]['total'] = $amounts[$contactId]['total']->plus($base);
            $totals[$bucket] = $totals[$bucket]->plus($base);
            $totals['total'] = $totals['total']->plus($base);
        }

        $rows = [];

        foreach ($amounts as $contactId => $buckets) {
            $rows[] = [
                'contact_id' => $contactId,
                'contact' => $names[$contactId],
                'invoices' => $counts[$contactId],
                ...array_map(fn (BigDecimal $value): string => Decimal::round($value), $buckets),
            ];
        }

        usort($rows, fn (array $a, array $b): int => Decimal::of($b['total'])->compareTo($a['total']));

        return [
            'contacts' => $rows,
            'totals' => array_map(fn (BigDecimal $value): string => Decimal::round($value), $totals),
        ];
    }

    public function export(InvoiceType $type, CarbonInterface $asOf): Report
    {
        $report = $this->generate($type, $asOf);

        $rows = array_map(fn (array $row): array => [
            $row['contact'], $row['current'], $row['1_30'], $row['31_60'], $row['61_90'], $row['over_90'], $row['total'],
        ], $report['contacts']);

        $totals = $report['totals'];
        $rows[] = [__('Total'), $totals['current'], $totals['1_30'], $totals['31_60'], $totals['61_90'], $totals['over_90'], $totals['total']];

        return new Report(
            $type->isSales() ? 'Aged Receivables' : 'Aged Payables',
            ['Contact', 'Not yet due', '1-30 days', '31-60 days', '61-90 days', 'Over 90 days', 'Total'],
            $rows,
            [__('As of :date', ['date' => $asOf->toDateString()])],
        );
    }

    protected function bucket(int $daysLate): string
    {
        foreach (self::BUCKETS as $key => [$from, $to]) {
            if (($from === null || $daysLate >= $from) && ($to === null || $daysLate <= $to)) {
                return $key;
            }
        }

        return 'over_90';
    }
}
