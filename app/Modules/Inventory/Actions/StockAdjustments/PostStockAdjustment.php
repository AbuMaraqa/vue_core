<?php

namespace App\Modules\Inventory\Actions\StockAdjustments;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Enums\DocumentType;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Accounting\Support\DocumentNumbers;
use App\Modules\Inventory\Data\StockAdjustmentData;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Support\StockLedger;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Record a stock adjustment, move the stock and post its value:
 *
 *   increase: Dr inventory, Cr the adjustment account
 *   decrease: Dr the adjustment account, Cr inventory
 *
 * The inventory side is grouped by inventory account, so a count of many
 * items gives a short entry.
 */
class PostStockAdjustment
{
    public function __construct(
        protected StockLedger $stock,
        protected Ledger $ledger,
        protected DocumentNumbers $numbers,
        protected DefaultAccounts $defaults,
    ) {}

    public function handle(StockAdjustmentData $data): StockAdjustment
    {
        return DB::transaction(function () use ($data): StockAdjustment {
            $this->ledger->ensureDateIsOpen($data->date);

            $userId = Auth::id();
            $adjustment = StockAdjustment::query()->create([
                'number' => $this->numbers->next(DocumentType::StockAdjustment, $data->date),
                'date' => $data->date,
                'warehouse_id' => $data->warehouse_id,
                'account_id' => $data->account_id ?? $this->defaults->id(AccountRole::InventoryAdjustment),
                'description' => $data->description,
                'status' => DocumentStatus::Posted,
                'created_by' => is_int($userId) ? $userId : null,
            ]);

            $items = Item::query()->with('category')->findMany(array_column($data->lines, 'item_id'))->keyBy('id');

            /** @var array<int, BigDecimal> $inventoryTotals */
            $inventoryTotals = [];

            foreach ($data->lines as $position => $line) {
                $item = $items[$line['item_id']];
                $quantity = Decimal::of($line['quantity']);

                $movement = $quantity->isPositive()
                    ? $this->stock->receive(
                        $item,
                        $data->warehouse_id,
                        $quantity,
                        filled($line['unit_cost'] ?? null) ? (string) $line['unit_cost'] : $this->currentCost($item, $data->warehouse_id),
                        $data->date,
                        StockMovementType::Adjustment,
                        $adjustment,
                        $data->description,
                    )
                    : $this->stock->issue($item, $data->warehouse_id, $quantity->abs(), $data->date, StockMovementType::Adjustment, $adjustment, $data->description);

                $adjustment->lines()->create([
                    'item_id' => $item->id,
                    'quantity' => $movement->quantity,
                    'unit_cost' => $movement->unit_cost,
                    'value' => $movement->value,
                    'sort' => $position,
                ]);

                $accountId = $item->inventoryAccountId();
                $inventoryTotals[$accountId] = ($inventoryTotals[$accountId] ?? Decimal::of(0))->plus($movement->value);
            }

            $this->postValue($adjustment, $inventoryTotals);

            return $adjustment;
        });
    }

    /**
     * @param  array<int, BigDecimal>  $inventoryTotals
     */
    protected function postValue(StockAdjustment $adjustment, array $inventoryTotals): void
    {
        $entry = JournalEntryBuilder::make($adjustment->date, __('Stock adjustment :number', ['number' => $adjustment->number]))
            ->reference($adjustment->number)
            ->source($adjustment);

        foreach ($inventoryTotals as $accountId => $total) {
            $entry->debit($accountId, $total);
        }

        $entry->credit($adjustment->account_id, Decimal::sum($inventoryTotals));

        if ($entry->hasLines()) {
            $entry->post();
        }
    }

    /**
     * The cost of a found quantity when none is entered: the warehouse's
     * average, else the item's purchase price.
     */
    protected function currentCost(Item $item, int $warehouseId): string
    {
        $average = StockLevel::query()->where('item_id', $item->id)->where('warehouse_id', $warehouseId)->value('average_cost');

        return Decimal::isPositive((string) $average) ? (string) $average : (string) $item->purchase_price;
    }
}
