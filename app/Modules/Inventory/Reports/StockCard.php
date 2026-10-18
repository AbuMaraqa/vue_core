<?php

namespace App\Modules\Inventory\Reports;

use App\Modules\Accounting\Support\Decimal;
use App\Modules\Foundation\Exports\Report;
use App\Modules\Foundation\Support\Filters\DateFilter;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * The stock card of an item: every movement of a period, in one warehouse
 * or all of them, with the running quantity and value.
 */
class StockCard
{
    /**
     * Very long cards are cut, with a notice, to keep the page responsive.
     */
    public const int MAX_LINES = 5000;

    /**
     * @return array{item: array<string, mixed>, opening_quantity: string, opening_value: string, closing_quantity: string, closing_value: string, total_in: string, total_out: string, lines: list<array<string, mixed>>, truncated: bool}
     */
    public function generate(Item $item, CarbonInterface $from, CarbonInterface $to, ?int $warehouseId = null): array
    {
        $movements = fn (): Builder => StockMovement::query()
            ->where('item_id', $item->id)
            ->when($warehouseId !== null, fn (Builder $query) => $query->where('warehouse_id', $warehouseId));

        $opening = DateFilter::before($movements(), 'date', $from)
            ->selectRaw('COALESCE(SUM(quantity), 0) as quantity, COALESCE(SUM(value), 0) as value')
            ->toBase()
            ->first();

        $lines = $movements()
            ->with(['warehouse', 'source'])
            ->tap(fn (Builder $query) => DateFilter::between($query, 'date', $from, $to))
            ->orderBy('date')
            ->orderBy('id')
            ->limit(self::MAX_LINES + 1)
            ->get();

        $quantity = Decimal::of((string) ($opening->quantity ?? '0'));
        $value = Decimal::of((string) ($opening->value ?? '0'));
        $openingQuantity = $quantity;
        $openingValue = $value;
        $totalIn = Decimal::of(0);
        $totalOut = Decimal::of(0);
        $rows = [];

        foreach ($lines->take(self::MAX_LINES) as $movement) {
            $moved = Decimal::of($movement->quantity);
            $quantity = $quantity->plus($moved);
            $value = $value->plus($movement->value);

            if ($moved->isPositive()) {
                $totalIn = $totalIn->plus($moved);
            } else {
                $totalOut = $totalOut->plus($moved->abs());
            }

            $number = $movement->source?->getAttribute('number');

            $rows[] = [
                'id' => $movement->id,
                'date' => $movement->date->toDateString(),
                'type' => $movement->type->label(),
                'document' => is_scalar($number) ? (string) $number : null,
                'warehouse' => $movement->warehouse->label(),
                'description' => $movement->description,
                'in' => $moved->isPositive() ? Decimal::round($moved) : null,
                'out' => $moved->isNegative() ? Decimal::round($moved->abs()) : null,
                'unit_cost' => Decimal::round($movement->unit_cost, 4),
                'value' => Decimal::round($movement->value),
                'balance_quantity' => Decimal::round($quantity),
                'balance_value' => Decimal::round($value),
            ];
        }

        return [
            'item' => ['id' => $item->id, 'label' => $item->label(), 'unit' => $item->unit?->translate('name')],
            'opening_quantity' => Decimal::round($openingQuantity),
            'opening_value' => Decimal::round($openingValue),
            'closing_quantity' => Decimal::round($quantity),
            'closing_value' => Decimal::round($value),
            'total_in' => Decimal::round($totalIn),
            'total_out' => Decimal::round($totalOut),
            'lines' => $rows,
            'truncated' => $lines->count() > self::MAX_LINES,
        ];
    }

    public function export(Item $item, CarbonInterface $from, CarbonInterface $to, ?int $warehouseId = null): Report
    {
        $card = $this->generate($item, $from, $to, $warehouseId);

        $rows = [['', '', '', __('Opening balance'), '', '', '', '', $card['opening_quantity'], $card['opening_value']]];

        foreach ($card['lines'] as $line) {
            $rows[] = [
                $line['date'], $line['type'], $line['document'], $line['description'] ?? $line['warehouse'],
                $line['in'], $line['out'], $line['unit_cost'], $line['value'], $line['balance_quantity'], $line['balance_value'],
            ];
        }

        $rows[] = ['', '', '', __('Closing balance'), $card['total_in'], $card['total_out'], '', '', $card['closing_quantity'], $card['closing_value']];

        $summary = [$item->label(), __(':from to :to', ['from' => $from->toDateString(), 'to' => $to->toDateString()])];

        if ($warehouseId !== null) {
            $summary[] = Warehouse::query()->find($warehouseId)?->label() ?? '';
        }

        return new Report(
            'Stock Card',
            ['Date', 'Type', 'Document', 'Description', 'In', 'Out', 'Unit cost', 'Value', 'Balance quantity', 'Balance value'],
            $rows,
            $summary,
        );
    }
}
