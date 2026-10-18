<?php

namespace App\Modules\Production\Actions\ProductionOrders;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Support\StockLedger;
use App\Modules\Production\Enums\ProductionOrderStatus;
use App\Modules\Production\Models\ProductionOrder;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Start a draft order: its materials leave their warehouse at the average
 * cost and wait in work in progress until the product is finished:
 *
 *   Dr work in progress   the value of the materials
 *   Cr inventory          per inventory account of the materials
 */
class StartProductionOrder
{
    public function __construct(
        protected StockLedger $stock,
        protected Ledger $ledger,
        protected DefaultAccounts $defaults,
    ) {}

    public function handle(ProductionOrder $order, CarbonInterface|string $date): ProductionOrder
    {
        return DB::transaction(function () use ($order, $date): ProductionOrder {
            $order = ProductionOrder::query()->lockForUpdate()->with(['materials.item.category'])->findOrFail($order->id);

            if ($order->status !== ProductionOrderStatus::Draft) {
                throw new AccountingException(__('Only draft production orders can be started.'));
            }

            if ($order->materials->isEmpty()) {
                throw new AccountingException(__('Add the materials of the production order first.'));
            }

            $this->ledger->ensureDateIsOpen($date);

            $workInProgress = $this->defaults->id(AccountRole::WorkInProgress);
            $description = __('Production order :number', ['number' => $order->number]);

            /** @var array<int, BigDecimal> $inventoryTotals */
            $inventoryTotals = [];

            foreach ($order->materials as $material) {
                $movement = $this->stock->issue(
                    $material->item,
                    $order->materials_warehouse_id,
                    $material->quantity,
                    $date,
                    StockMovementType::ProductionIssue,
                    $order,
                    $description,
                );

                $value = Decimal::of($movement->value)->abs();

                $material->update([
                    'unit_cost' => $movement->unit_cost,
                    'issued_value' => Decimal::round($value),
                    'value' => Decimal::round($value),
                ]);

                $accountId = $material->item->inventoryAccountId();
                $inventoryTotals[$accountId] = ($inventoryTotals[$accountId] ?? Decimal::of(0))->plus($value);
            }

            $entry = JournalEntryBuilder::make($date, __('Materials issued to production order :number', ['number' => $order->number]))
                ->reference($order->number)
                ->source($order)
                ->debit($workInProgress, Decimal::sum($inventoryTotals), costCenterId: $order->cost_center_id);

            foreach ($inventoryTotals as $accountId => $total) {
                $entry->credit($accountId, $total);
            }

            if ($entry->hasLines()) {
                $entry->post();
            }

            $order->update([
                'status' => ProductionOrderStatus::InProgress,
                'started_on' => $date,
            ]);

            return $order;
        });
    }
}
