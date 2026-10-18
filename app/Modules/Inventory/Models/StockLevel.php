<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What one warehouse holds of one item now: the quantity, its value and the
 * weighted average cost of a unit. Only StockLedger changes it.
 *
 * @property int $id
 * @property int $item_id
 * @property int $warehouse_id
 * @property string $quantity
 * @property string $average_cost
 * @property string $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Item $item
 * @property-read Warehouse $warehouse
 */
#[Fillable(['item_id', 'warehouse_id', 'quantity', 'average_cost', 'value'])]
class StockLevel extends Model
{
    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'average_cost' => 'decimal:6',
            'value' => 'decimal:4',
        ];
    }
}
