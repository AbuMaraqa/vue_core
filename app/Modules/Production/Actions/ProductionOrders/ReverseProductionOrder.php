<?php

namespace App\Modules\Production\Actions\ProductionOrders;

use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Support\StockLedger;
use App\Modules\Production\Enums\ProductionOrderStatus;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOrderMaterial;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Step an order back one stage, reversing that stage's stock movements and
 * journal entry on its own date while the period is open, else today:
 *
 * - completed → in progress: the product leaves its warehouse (it must still
 *   be there), extra materials go back and returned ones are issued again;
 * - in progress → draft: the materials go back to their warehouse.
 *
 * The draft can then be corrected and started again, or cancelled.
 */
class ReverseProductionOrder
{
    public function __construct(
        protected StockLedger $stock,
        protected Ledger $ledger,
    ) {}

    public function handle(ProductionOrder $order): ProductionOrder
    {
        return DB::transaction(function () use ($order): ProductionOrder {
            $order = ProductionOrder::query()->lockForUpdate()->with('materials')->findOrFail($order->id);

            return match ($order->status) {
                ProductionOrderStatus::Completed => $this->reopen($order),
                ProductionOrderStatus::InProgress => $this->backToDraft($order),
                default => throw new AccountingException(__('Only started production orders can be reversed.')),
            };
        });
    }

    protected function reopen(ProductionOrder $order): ProductionOrder
    {
        $date = $this->ledger->openDateFor($order->completed_on ?? now());

        $this->stock->reverse($order, $date, [
            StockMovementType::ProductionOutput,
            StockMovementType::ProductionReturn,
            StockMovementType::ProductionExtraIssue,
        ]);
        $this->reverseLatestEntry($order, $date);

        $order->materials
            ->filter(fn (ProductionOrderMaterial $material): bool => Decimal::of($material->quantity)->isZero())
            ->each(fn (ProductionOrderMaterial $material) => $material->delete());

        $order->materials()->update(['consumed_quantity' => null, 'value' => DB::raw('issued_value')]);

        $order->update([
            'status' => ProductionOrderStatus::InProgress,
            'completed_on' => null,
            'produced_quantity' => null,
            'materials_cost' => '0',
            'other_costs' => '0',
            'total_cost' => '0',
            'unit_cost' => '0',
        ]);

        return $order;
    }

    protected function backToDraft(ProductionOrder $order): ProductionOrder
    {
        $date = $this->ledger->openDateFor($order->started_on ?? now());

        $this->stock->reverse($order, $date, [StockMovementType::ProductionIssue]);
        $this->reverseLatestEntry($order, $date);

        $order->materials()->update(['unit_cost' => '0', 'issued_value' => '0', 'value' => '0']);

        $order->update([
            'status' => ProductionOrderStatus::Draft,
            'started_on' => null,
        ]);

        return $order;
    }

    /**
     * The entry of the stage being undone is the latest one still posted: a
     * completion always posts when a start did, so it is never skipped.
     */
    protected function reverseLatestEntry(ProductionOrder $order, CarbonInterface|string $date): void
    {
        $latest = $order->journalEntries()
            ->where('status', JournalEntryStatus::Posted)
            ->whereNull('reversal_of_id')
            ->latest('id')
            ->first();

        if ($latest !== null) {
            $this->ledger->reverse($latest, $date, __('Reversal of :number', ['number' => $order->number]));
        }
    }
}
