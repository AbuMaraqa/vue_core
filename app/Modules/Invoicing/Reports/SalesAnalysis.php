<?php

namespace App\Modules\Invoicing\Reports;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Foundation\Exports\Report;
use App\Modules\Foundation\Support\Filters\DateFilter;
use App\Modules\Invoicing\Enums\InvoiceType;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Net sales (or purchases) of a period by contact or by item, in the base
 * currency: invoices minus credit notes (bills minus debit notes), before
 * tax. For sales by item, the cost of what was sold and the gross margin.
 */
class SalesAnalysis
{
    /**
     * @param  'contact'|'item'  $groupBy
     * @return array{rows: list<array<string, mixed>>, totals: array{quantity: string, net: string, cost: string, margin: string}}
     */
    public function generate(bool $sales, string $groupBy, CarbonInterface $from, CarbonInterface $to): array
    {
        [$document, $return] = $sales
            ? [InvoiceType::SalesInvoice, InvoiceType::SalesCreditNote]
            : [InvoiceType::PurchaseBill, InvoiceType::PurchaseDebitNote];

        $sign = "CASE WHEN invoices.type = '{$return->value}' THEN -1 ELSE 1 END";

        $rows = DB::table('invoice_lines')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoices.status', DocumentStatus::Posted->value)
            ->where('invoices.is_opening', false)
            ->whereIn('invoices.type', [$document->value, $return->value])
            ->tap(fn (Builder $query) => DateFilter::between($query, 'invoices.date', $from, $to))
            ->when($groupBy === 'item', fn (Builder $query) => $query
                ->join('items', 'items.id', '=', 'invoice_lines.item_id')
                ->groupBy('items.id', 'items.code', 'items.name')
                ->select('items.id as key', 'items.code', 'items.name'))
            ->when($groupBy === 'contact', fn (Builder $query) => $query
                ->join('contacts', 'contacts.id', '=', 'invoices.contact_id')
                ->groupBy('contacts.id', 'contacts.code', 'contacts.name')
                ->select('contacts.id as key', 'contacts.code', 'contacts.name'))
            ->selectRaw("SUM({$sign} * invoice_lines.quantity) as quantity")
            ->selectRaw("SUM({$sign} * invoice_lines.subtotal * invoices.exchange_rate) as net")
            ->selectRaw("SUM({$sign} * COALESCE(invoice_lines.unit_cost, 0) * invoice_lines.quantity) as cost")
            ->selectRaw('COUNT(DISTINCT invoices.id) as documents')
            ->get();

        $totals = ['quantity' => BigDecimal::zero(), 'net' => BigDecimal::zero(), 'cost' => BigDecimal::zero()];
        $result = [];

        foreach ($rows as $row) {
            $net = Decimal::of(Decimal::round((string) $row->net));
            $cost = Decimal::of(Decimal::round((string) $row->cost));
            $name = is_string($row->name) && str_starts_with($row->name, '{') ? json_decode($row->name, true) : $row->name;

            $result[] = [
                'key' => (int) $row->key,
                'code' => $row->code,
                'name' => is_array($name) ? ($name[app()->getLocale()] ?? reset($name) ?: '') : (string) $name,
                'documents' => (int) $row->documents,
                'quantity' => Decimal::round((string) $row->quantity),
                'net' => Decimal::round($net),
                'cost' => $groupBy === 'item' && $sales ? Decimal::round($cost) : null,
                'margin' => $groupBy === 'item' && $sales ? Decimal::round($net->minus($cost)) : null,
            ];

            $totals['quantity'] = $totals['quantity']->plus((string) $row->quantity);
            $totals['net'] = $totals['net']->plus($net);
            $totals['cost'] = $totals['cost']->plus($cost);
        }

        usort($result, fn (array $a, array $b): int => Decimal::of($b['net'])->compareTo($a['net']));

        return [
            'rows' => $result,
            'totals' => [
                'quantity' => Decimal::round($totals['quantity']),
                'net' => Decimal::round($totals['net']),
                'cost' => Decimal::round($totals['cost']),
                'margin' => Decimal::round($totals['net']->minus($totals['cost'])),
            ],
        ];
    }

    /**
     * @param  'contact'|'item'  $groupBy
     */
    public function export(bool $sales, string $groupBy, CarbonInterface $from, CarbonInterface $to): Report
    {
        $report = $this->generate($sales, $groupBy, $from, $to);
        $withMargin = $groupBy === 'item' && $sales;

        $rows = array_map(fn (array $row): array => array_values(array_filter([
            $row['code'], $row['name'], $row['documents'], $row['quantity'], $row['net'],
            $withMargin ? $row['cost'] : false, $withMargin ? $row['margin'] : false,
        ], fn (mixed $value): bool => $value !== false)), $report['rows']);

        return new Report(
            $sales ? ($groupBy === 'item' ? 'Sales by Item' : 'Sales by Customer') : ($groupBy === 'item' ? 'Purchases by Item' : 'Purchases by Vendor'),
            $withMargin
                ? ['Code', 'Name', 'Documents', 'Quantity', 'Net amount', 'Cost', 'Gross margin']
                : ['Code', 'Name', 'Documents', 'Quantity', 'Net amount'],
            $rows,
            [__(':from to :to', ['from' => $from->toDateString(), 'to' => $to->toDateString()])],
        );
    }
}
