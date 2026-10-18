<?php

namespace App\Modules\Inventory\Reports;

use App\Modules\Accounting\Support\Decimal;
use App\Modules\Foundation\Exports\Report;
use App\Modules\Foundation\Support\Filters\DateFilter;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The quantity and value of every item on a date, from the stock ledger:
 * what the inventory account of the balance sheet is made of.
 */
class StockValuation
{
    /**
     * @return array{rows: list<array<string, mixed>>, total_value: string}
     */
    public function generate(CarbonInterface $asOf, ?int $warehouseId = null, ?int $categoryId = null, bool $includeZero = false): array
    {
        $totals = DB::table('stock_movements')
            ->join('items', 'items.id', '=', 'stock_movements.item_id')
            ->tap(fn (Builder $query) => DateFilter::onOrBefore($query, 'stock_movements.date', $asOf))
            ->when($warehouseId !== null, fn (Builder $query) => $query->where('stock_movements.warehouse_id', $warehouseId))
            ->when($categoryId !== null, fn (Builder $query) => $query->where('items.category_id', $categoryId))
            ->groupBy('stock_movements.item_id')
            ->select('stock_movements.item_id')
            ->selectRaw('SUM(stock_movements.quantity) as quantity, SUM(stock_movements.value) as value')
            ->get()
            ->keyBy('item_id');

        $items = Item::query()
            ->with(['unit', 'category'])
            ->where('type', 'stock')
            ->when($categoryId !== null, fn ($query) => $query->where('category_id', $categoryId))
            ->when(! $includeZero, fn ($query) => $query->whereIn('id', $totals->keys()))
            ->orderBy('code')
            ->get();

        $rows = [];
        $totalValue = Decimal::of(0);

        foreach ($items as $item) {
            $quantity = Decimal::of((string) ($totals[$item->id]->quantity ?? '0'));
            $value = Decimal::of((string) ($totals[$item->id]->value ?? '0'));

            if (! $includeZero && $quantity->isZero() && $value->isZero()) {
                continue;
            }

            $totalValue = $totalValue->plus($value);
            $reorderLevel = $item->reorder_level;

            $rows[] = [
                'item_id' => $item->id,
                'code' => $item->code,
                'name' => $item->translate('name'),
                'category' => $item->category?->translate('name'),
                'unit' => $item->unit?->translate('name'),
                'quantity' => Decimal::round($quantity),
                'average_cost' => $quantity->isPositive() ? Decimal::divide($value, $quantity, 4) : '0.0000',
                'value' => Decimal::round($value),
                'below_reorder' => $reorderLevel !== null && $quantity->compareTo(Decimal::of($reorderLevel)) <= 0,
            ];
        }

        return ['rows' => $rows, 'total_value' => Decimal::round($totalValue)];
    }

    public function export(CarbonInterface $asOf, ?int $warehouseId = null, ?int $categoryId = null, bool $includeZero = false): Report
    {
        $report = $this->generate($asOf, $warehouseId, $categoryId, $includeZero);

        $rows = array_map(fn (array $row): array => [
            $row['code'], $row['name'], $row['category'], $row['unit'], $row['quantity'], $row['average_cost'], $row['value'],
        ], $report['rows']);

        $rows[] = ['', __('Total'), '', '', '', '', $report['total_value']];

        $summary = [__('As of :date', ['date' => $asOf->toDateString()])];

        if ($warehouseId !== null) {
            $summary[] = Warehouse::query()->find($warehouseId)?->label() ?? '';
        }

        return new Report(
            'Stock Valuation',
            ['Code', 'Item', 'Category', 'Unit', 'Quantity', 'Average cost', 'Value'],
            $rows,
            $summary,
        );
    }
}
