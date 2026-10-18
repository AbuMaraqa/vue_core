<?php

namespace App\Modules\Production\Reports;

use App\Modules\Accounting\Support\Decimal;
use App\Modules\Foundation\Exports\Report;
use App\Modules\Production\Enums\ProductionOrderStatus;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOrderMaterial;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * What was produced in a period and what it cost: every completed order,
 * the totals per product (with the average unit cost), and the materials
 * consumed.
 */
class ProductionSummary
{
    /**
     * @return array{
     *     orders: list<array<string, mixed>>,
     *     products: list<array<string, mixed>>,
     *     materials: list<array<string, mixed>>,
     *     totals: array{materials_cost: string, other_costs: string, total_cost: string}
     * }
     */
    public function generate(CarbonInterface $from, CarbonInterface $to, ?int $itemId = null): array
    {
        $orders = $this->orders($from, $to, $itemId);

        $products = array_values($orders->groupBy('item_id')->map(function (BaseCollection $group): array {
            $quantity = Decimal::sum($group->pluck('produced_quantity'));
            $total = Decimal::sum($group->pluck('total_cost'));
            $first = $group->first();

            return [
                'item_id' => $first->item_id,
                'item' => $first->item->label(),
                'unit' => $first->item->unit?->translate('name'),
                'orders' => $group->count(),
                'quantity' => Decimal::round($quantity),
                'total_cost' => Decimal::round($total),
                'average_unit_cost' => $quantity->isPositive() ? Decimal::divide($total, $quantity, 4) : '0.0000',
            ];
        })->sortBy('item')->all());

        $materials = $this->materials($orders);

        return [
            'orders' => array_values($orders->map(fn (ProductionOrder $order): array => [
                'id' => $order->id,
                'number' => $order->number,
                'completed_on' => $order->completed_on?->toDateString(),
                'item' => $order->item->label(),
                'unit' => $order->item->unit?->translate('name'),
                'contact' => $order->contact?->label(),
                'produced_quantity' => (string) $order->produced_quantity,
                'materials_cost' => (string) $order->materials_cost,
                'other_costs' => (string) $order->other_costs,
                'total_cost' => (string) $order->total_cost,
                'unit_cost' => Decimal::round($order->unit_cost),
            ])->all()),
            'products' => $products,
            'materials' => $materials,
            'totals' => [
                'materials_cost' => Decimal::round(Decimal::sum($orders->pluck('materials_cost'))),
                'other_costs' => Decimal::round(Decimal::sum($orders->pluck('other_costs'))),
                'total_cost' => Decimal::round(Decimal::sum($orders->pluck('total_cost'))),
            ],
        ];
    }

    public function export(CarbonInterface $from, CarbonInterface $to, ?int $itemId = null): Report
    {
        $report = $this->generate($from, $to, $itemId);

        $rows = array_map(fn (array $row): array => [
            $row['number'], $row['completed_on'], $row['item'], $row['contact'], $row['produced_quantity'],
            $row['materials_cost'], $row['other_costs'], $row['total_cost'], $row['unit_cost'],
        ], $report['orders']);

        $rows[] = ['', '', __('Total'), '', '', $report['totals']['materials_cost'], $report['totals']['other_costs'], $report['totals']['total_cost'], ''];

        return new Report(
            'Production Report',
            ['Number', 'Completed on', 'Product', 'Customer', 'Produced quantity', 'Materials cost', 'Other costs', 'Total cost', 'Unit cost'],
            $rows,
            [__(':from to :to', ['from' => $from->toDateString(), 'to' => $to->toDateString()])],
        );
    }

    /**
     * The materials consumed by the orders, summed per item by the database,
     * the most valuable first.
     *
     * @param  Collection<int, ProductionOrder>  $orders
     * @return list<array{item_id: int, item: string, unit: string|null, quantity: string, value: string}>
     */
    protected function materials(Collection $orders): array
    {
        if ($orders->isEmpty()) {
            return [];
        }

        return array_values(ProductionOrderMaterial::query()
            ->with('item.unit')
            ->whereIn('production_order_id', $orders->modelKeys())
            ->groupBy('item_id')
            ->select('item_id')
            ->selectRaw('SUM(consumed_quantity) as total_quantity')
            ->selectRaw('SUM(value) as total_value')
            ->get()
            ->map(fn (ProductionOrderMaterial $material): array => [
                'item_id' => $material->item_id,
                'item' => $material->item->label(),
                'unit' => $material->item->unit?->translate('name'),
                'quantity' => Decimal::round((string) ($material->getAttribute('total_quantity') ?? '0')),
                'value' => Decimal::round((string) ($material->getAttribute('total_value') ?? '0')),
            ])
            ->sortByDesc(fn (array $row): float => (float) $row['value'])
            ->all());
    }

    /**
     * @return Collection<int, ProductionOrder>
     */
    protected function orders(CarbonInterface $from, CarbonInterface $to, ?int $itemId): Collection
    {
        return ProductionOrder::query()
            ->with(['item.unit', 'contact'])
            ->where('status', ProductionOrderStatus::Completed)
            ->whereBetween('completed_on', [$from->toDateString(), $to->toDateString()])
            ->when($itemId !== null, fn (Builder $query) => $query->where('item_id', $itemId))
            ->orderBy('completed_on')
            ->orderBy('id')
            ->get();
    }
}
