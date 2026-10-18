<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Settings\AccountingSettings;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The only way stock changes. Every receipt and issue is a movement, and the
 * stock level of the item in the warehouse is updated with it, at a weighted
 * average cost:
 *
 * - a receipt adds its quantity and value; the new average is
 *   `value / quantity`;
 * - an issue takes its quantity at the current average (all the remaining
 *   value when the warehouse is emptied, so no cents are left behind);
 * - a reversal undoes a movement at exactly its own quantity and value.
 *
 * So the value of a stock level is always the sum of its movements, which is
 * also what the documents posted to the inventory account. Costs follow the
 * order movements are recorded in.
 *
 * Levels are locked while they change, so parallel documents queue up.
 */
class StockLedger
{
    public const int COST_SCALE = 6;

    public function __construct(protected AccountingSettings $settings) {}

    /**
     * Bring stock in at a unit cost, e.g. a purchase, a return from a
     * customer, or a found quantity. `$value` replaces `quantity × cost`
     * when the exact amount is known, e.g. the value that left another
     * warehouse.
     */
    public function receive(
        Item $item,
        Warehouse|int $warehouse,
        BigNumber|int|string $quantity,
        BigNumber|int|string $unitCost,
        CarbonInterface|string $date,
        StockMovementType $type,
        ?Model $source = null,
        ?string $description = null,
        BigNumber|string|null $value = null,
    ): StockMovement {
        $quantity = $this->positiveQuantity($item, $quantity);
        $unitCost = Decimal::of($unitCost);

        if ($unitCost->isNegative()) {
            throw new AccountingException(__('The cost of :item cannot be negative.', ['item' => $item->label()]));
        }

        return DB::transaction(function () use ($item, $warehouse, $quantity, $unitCost, $date, $type, $source, $description, $value): StockMovement {
            $level = $this->lockedLevel($item, $warehouse);
            $value = Decimal::of(Decimal::round($value ?? $quantity->multipliedBy($unitCost)));

            $this->apply($level, $quantity, $value, $unitCost);

            return $this->record($level, $date, $type, $quantity, $unitCost, $value, $source, $description);
        });
    }

    /**
     * Take stock out at the average cost, e.g. a sale or a lost quantity. The
     * movement's value (negative) is the cost to post.
     */
    public function issue(
        Item $item,
        Warehouse|int $warehouse,
        BigNumber|int|string $quantity,
        CarbonInterface|string $date,
        StockMovementType $type,
        ?Model $source = null,
        ?string $description = null,
    ): StockMovement {
        $quantity = $this->positiveQuantity($item, $quantity);

        return DB::transaction(function () use ($item, $warehouse, $quantity, $date, $type, $source, $description): StockMovement {
            $level = $this->lockedLevel($item, $warehouse);
            $onHand = Decimal::of($level->quantity);

            $this->ensureAvailable($item, $level, $quantity);

            $unitCost = Decimal::of($level->average_cost);

            if ($unitCost->isZero() && ! $onHand->isPositive()) {
                $unitCost = Decimal::of($item->purchase_price);
            }

            $value = $quantity->compareTo($onHand) === 0
                ? Decimal::of($level->value)
                : Decimal::of(Decimal::round($quantity->multipliedBy($unitCost)));

            $this->apply($level, $quantity->negated(), $value->negated(), $unitCost);

            return $this->record($level, $date, $type, $quantity->negated(), $unitCost, $value->negated(), $source, $description);
        });
    }

    /**
     * Undo every movement of a document, newest first, e.g. when it is
     * cancelled. Received stock that was already used cannot be reversed
     * unless negative stock is allowed.
     *
     * Pass `$types` to undo only the movements of those types, e.g. one stage
     * of a document that moves stock in several steps.
     *
     * @param  list<StockMovementType>  $types
     * @return list<StockMovement> The reversing movements.
     */
    public function reverse(Model $source, CarbonInterface|string|null $date = null, array $types = []): array
    {
        return DB::transaction(function () use ($source, $date, $types): array {
            $movements = StockMovement::query()
                ->with('item')
                ->where('source_type', $source->getMorphClass())
                ->where('source_id', $source->getKey())
                ->whereNull('reversal_of_id')
                ->where('is_reversed', false)
                ->when($types !== [], fn ($query) => $query->whereIn('type', $types))
                ->orderByDesc('id')
                ->get();

            $reversals = [];

            foreach ($movements as $movement) {
                $level = $this->lockedLevel($movement->item, $movement->warehouse_id);
                $quantity = Decimal::of($movement->quantity)->negated();

                if ($quantity->isNegative()) {
                    $this->ensureAvailable($movement->item, $level, $quantity->abs());
                }

                $value = Decimal::of($movement->value)->negated();
                $unitCost = Decimal::of($movement->unit_cost);

                $this->apply($level, $quantity, $value, $unitCost);

                $reversal = $this->record(
                    $level,
                    $date ?? now(),
                    StockMovementType::Reversal,
                    $quantity,
                    $unitCost,
                    $value,
                    $source,
                    __('Reversal of :type', ['type' => $movement->type->label()]),
                );

                $reversal->forceFill(['reversal_of_id' => $movement->id])->save();
                $movement->forceFill(['is_reversed' => true])->save();

                $reversals[] = $reversal;
            }

            return $reversals;
        });
    }

    /**
     * The quantity on hand of an item, in one warehouse or in all of them.
     */
    public function onHand(Item|int $item, Warehouse|int|null $warehouse = null): string
    {
        $quantity = StockLevel::query()
            ->where('item_id', $item instanceof Item ? $item->id : $item)
            ->when($warehouse !== null, fn ($query) => $query->where('warehouse_id', $warehouse instanceof Warehouse ? $warehouse->id : $warehouse))
            ->sum('quantity');

        return Decimal::round((string) $quantity);
    }

    /**
     * Add a signed quantity and value to the level and work out the new
     * average. While the quantity is not positive the average is the last
     * unit cost, so the next issue still has a sensible cost.
     */
    protected function apply(StockLevel $level, BigDecimal $quantity, BigDecimal $value, BigDecimal $unitCost): void
    {
        $newQuantity = Decimal::of($level->quantity)->plus($quantity);
        $newValue = Decimal::of($level->value)->plus($value);

        $average = $newQuantity->isPositive()
            ? Decimal::divide($newValue, $newQuantity, self::COST_SCALE)
            : Decimal::round($unitCost, self::COST_SCALE);

        $level->forceFill([
            'quantity' => Decimal::round($newQuantity),
            'value' => Decimal::round($newValue),
            'average_cost' => $average,
        ])->save();
    }

    protected function ensureAvailable(Item $item, StockLevel $level, BigDecimal $quantity): void
    {
        if ($this->settings->allow_negative_stock || $quantity->compareTo(Decimal::of($level->quantity)) <= 0) {
            return;
        }

        throw new AccountingException(__('Not enough :item in :warehouse: :available available, :requested needed.', [
            'item' => $item->label(),
            'warehouse' => $level->warehouse->label(),
            'available' => Decimal::round($level->quantity),
            'requested' => Decimal::round($quantity),
        ]));
    }

    protected function positiveQuantity(Item $item, BigNumber|int|string $quantity): BigDecimal
    {
        if (! $item->isStock()) {
            throw new AccountingException(__(':item is a service and has no stock.', ['item' => $item->label()]));
        }

        $quantity = Decimal::of($quantity);

        if (! $quantity->isPositive()) {
            throw new AccountingException(__('The quantity of :item must be more than zero.', ['item' => $item->label()]));
        }

        return $quantity;
    }

    protected function lockedLevel(Item $item, Warehouse|int $warehouse): StockLevel
    {
        $warehouseId = $warehouse instanceof Warehouse ? $warehouse->id : $warehouse;
        $query = fn (): ?StockLevel => StockLevel::query()
            ->with('warehouse')
            ->where('item_id', $item->id)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        $level = $query();

        if ($level !== null) {
            return $level;
        }

        try {
            StockLevel::query()->create(['item_id' => $item->id, 'warehouse_id' => $warehouseId]);
        } catch (UniqueConstraintViolationException) {
            // Another request created it first; use theirs.
        }

        return $query() ?? throw new AccountingException(__('The warehouse could not be found.'));
    }

    protected function record(
        StockLevel $level,
        CarbonInterface|string $date,
        StockMovementType $type,
        BigDecimal $quantity,
        BigDecimal $unitCost,
        BigDecimal $value,
        ?Model $source,
        ?string $description,
    ): StockMovement {
        return StockMovement::query()->create([
            'item_id' => $level->item_id,
            'warehouse_id' => $level->warehouse_id,
            'date' => Carbon::parse($date)->toDateString(),
            'type' => $type,
            'quantity' => Decimal::round($quantity),
            'unit_cost' => Decimal::round($unitCost, self::COST_SCALE),
            'value' => Decimal::round($value),
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'description' => $description,
        ]);
    }
}
