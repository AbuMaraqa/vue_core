<?php

namespace App\Modules\Production\Actions\ProductionOrders;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Support\StockLedger;
use App\Modules\Production\Data\ProductionCompletionData;
use App\Modules\Production\Enums\ProductionOrderStatus;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOrderMaterial;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Finish an order that is in progress, with what really happened:
 *
 * - a material used beyond what was issued is issued now, at the average
 *   cost; what was not used goes back to the warehouse at the cost it left
 *   with, so the materials cost is exactly what was consumed;
 * - the other costs (labour, painting...) are added;
 * - the product comes into its warehouse at that full cost, so its unit
 *   cost is `total cost ÷ produced quantity`.
 *
 *   Dr product inventory   the full cost
 *   Cr work in progress    the materials issued at the start
 *   Cr / Dr inventory      materials issued now / returned now
 *   Cr each cost account   the other costs
 */
class CompleteProductionOrder
{
    public function __construct(
        protected StockLedger $stock,
        protected Ledger $ledger,
        protected DefaultAccounts $defaults,
    ) {}

    public function handle(ProductionOrder $order, ProductionCompletionData $data): ProductionOrder
    {
        return DB::transaction(function () use ($order, $data): ProductionOrder {
            $order = ProductionOrder::query()->lockForUpdate()->with(['item.category', 'materials.item.category'])->findOrFail($order->id);

            if ($order->status !== ProductionOrderStatus::InProgress) {
                throw new AccountingException(__('Only production orders in progress can be completed.'));
            }

            if ($order->started_on !== null && Carbon::parse($data->date)->lessThan($order->started_on)) {
                throw new AccountingException(__('The production order cannot be completed before it was started.'));
            }

            $this->ledger->ensureDateIsOpen($data->date);

            $workInProgress = $this->defaults->id(AccountRole::WorkInProgress);
            $description = __('Production order :number', ['number' => $order->number]);

            /** @var array<int, BigDecimal> $inventoryChanges What left (+) or came back to (−) each inventory account now. */
            $inventoryChanges = [];
            $issuedAtStart = Decimal::sum($order->materials->pluck('issued_value'));

            $this->settleIssuedMaterials($order, $data, $description, $inventoryChanges);
            $this->issueNewMaterials($order, $data, $description, $inventoryChanges);

            $order->costs()->delete();

            foreach ($data->costs as $position => $cost) {
                $order->costs()->create([
                    'description' => $cost['description'],
                    'account_id' => $cost['account_id'],
                    'amount' => $cost['amount'],
                    'sort' => $position,
                ]);
            }

            $order->load(['materials', 'costs']);

            $materialsCost = Decimal::sum($order->materials->pluck('value'));
            $otherCosts = Decimal::sum($order->costs->pluck('amount'));
            $totalCost = $materialsCost->plus($otherCosts);
            $unitCost = Decimal::divide($totalCost, $data->produced_quantity, StockLedger::COST_SCALE);

            $this->stock->receive(
                $order->item,
                $order->product_warehouse_id,
                $data->produced_quantity,
                $unitCost,
                $data->date,
                StockMovementType::ProductionOutput,
                $order,
                $description,
                Decimal::round($totalCost),
            );

            $entry = JournalEntryBuilder::make($data->date, __('Production order :number completed', ['number' => $order->number]))
                ->reference($order->number)
                ->source($order)
                ->debit($order->item->inventoryAccountId(), $totalCost)
                ->credit($workInProgress, $issuedAtStart, costCenterId: $order->cost_center_id);

            foreach ($inventoryChanges as $accountId => $change) {
                $entry->credit($accountId, $change);
            }

            foreach ($order->costs as $cost) {
                $entry->credit($cost->account_id, $cost->amount, $cost->description, costCenterId: $order->cost_center_id);
            }

            if ($entry->hasLines()) {
                $entry->post();
            }

            $order->update([
                'status' => ProductionOrderStatus::Completed,
                'completed_on' => $data->date,
                'produced_quantity' => $data->produced_quantity,
                'materials_cost' => Decimal::round($materialsCost),
                'other_costs' => Decimal::round($otherCosts),
                'total_cost' => Decimal::round($totalCost),
                'unit_cost' => $unitCost,
            ]);

            return $order;
        });
    }

    /**
     * Issue the extra quantity of the materials used beyond what was issued,
     * and return what was not used.
     *
     * @param  array<int, BigDecimal>  $inventoryChanges
     */
    protected function settleIssuedMaterials(ProductionOrder $order, ProductionCompletionData $data, string $description, array &$inventoryChanges): void
    {
        $consumed = collect($data->materials)
            ->filter(fn (array $line): bool => filled($line['id'] ?? null))
            ->mapWithKeys(fn (array $line): array => [(int) $line['id'] => (string) $line['consumed_quantity']]);

        foreach ($order->materials as $material) {
            if (! $consumed->has($material->id)) {
                throw new AccountingException(__('Enter the quantity used of :item.', ['item' => $material->item->label()]));
            }

            $used = Decimal::of($consumed[$material->id]);
            $difference = $used->minus($material->quantity);
            $value = Decimal::of($material->issued_value);
            $accountId = $material->item->inventoryAccountId();

            if ($difference->isPositive()) {
                $movement = $this->stock->issue($material->item, $order->materials_warehouse_id, $difference, $data->date, StockMovementType::ProductionExtraIssue, $order, $description);
                $extra = Decimal::of($movement->value)->abs();
                $value = $value->plus($extra);
                $inventoryChanges[$accountId] = ($inventoryChanges[$accountId] ?? Decimal::of(0))->plus($extra);
            }

            if ($difference->isNegative()) {
                $movement = $this->stock->receive(
                    $material->item,
                    $order->materials_warehouse_id,
                    $difference->abs(),
                    $material->unit_cost,
                    $data->date,
                    StockMovementType::ProductionReturn,
                    $order,
                    $description,
                    $used->isZero() ? $material->issued_value : null,
                );
                $returned = Decimal::of($movement->value);
                $value = $value->minus($returned);
                $inventoryChanges[$accountId] = ($inventoryChanges[$accountId] ?? Decimal::of(0))->minus($returned);
            }

            $material->update([
                'consumed_quantity' => Decimal::round($used),
                'value' => Decimal::round($value),
            ]);
        }
    }

    /**
     * Issue the materials that were not planned but were used.
     *
     * @param  array<int, BigDecimal>  $inventoryChanges
     */
    protected function issueNewMaterials(ProductionOrder $order, ProductionCompletionData $data, string $description, array &$inventoryChanges): void
    {
        $lines = array_values(array_filter($data->materials, fn (array $line): bool => blank($line['id'] ?? null)));
        $items = Item::query()->with('category')->findMany(array_column($lines, 'item_id'))->keyBy('id');
        $sort = (int) $order->materials->max('sort');

        foreach ($lines as $line) {
            $item = $items[$line['item_id']];
            $movement = $this->stock->issue($item, $order->materials_warehouse_id, $line['consumed_quantity'], $data->date, StockMovementType::ProductionExtraIssue, $order, $description);
            $value = Decimal::of($movement->value)->abs();

            $order->materials()->save(new ProductionOrderMaterial([
                'item_id' => $item->id,
                'quantity' => '0',
                'unit_cost' => $movement->unit_cost,
                'issued_value' => '0',
                'consumed_quantity' => Decimal::round($line['consumed_quantity']),
                'value' => Decimal::round($value),
                'sort' => ++$sort,
            ]));

            $accountId = $item->inventoryAccountId();
            $inventoryChanges[$accountId] = ($inventoryChanges[$accountId] ?? Decimal::of(0))->plus($value);
        }
    }
}
