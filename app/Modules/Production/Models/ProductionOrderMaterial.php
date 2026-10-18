<?php

namespace App\Modules\Production\Models;

use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A material of a production order. `quantity` is what is issued when the
 * order starts, at the warehouse's average `unit_cost` (`issued_value`).
 * When it completes, `consumed_quantity` is what was really used: more is
 * issued, or the rest is returned to the warehouse, and `value` is the cost
 * of what was used.
 *
 * @property int $id
 * @property int $production_order_id
 * @property int $item_id
 * @property string $quantity
 * @property string $unit_cost
 * @property string $issued_value
 * @property string|null $consumed_quantity
 * @property string $value
 * @property int $sort
 * @property-read Item $item
 */
#[Fillable(['production_order_id', 'item_id', 'quantity', 'unit_cost', 'issued_value', 'consumed_quantity', 'value', 'sort'])]
class ProductionOrderMaterial extends Model
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
            'issued_value' => 'decimal:4',
            'consumed_quantity' => 'decimal:4',
            'value' => 'decimal:4',
        ];
    }
}
