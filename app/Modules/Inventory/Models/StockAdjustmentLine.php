<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item of an adjustment: a signed quantity (positive adds stock) and the
 * value it moved. Increases are valued at the entered unit cost, decreases
 * at the average cost of the warehouse.
 *
 * @property int $id
 * @property int $stock_adjustment_id
 * @property int $item_id
 * @property string $quantity
 * @property string $unit_cost
 * @property string $value
 * @property int $sort
 * @property-read Item $item
 */
#[Fillable(['stock_adjustment_id', 'item_id', 'quantity', 'unit_cost', 'value', 'sort'])]
class StockAdjustmentLine extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'value' => 'decimal:4',
        ];
    }
}
