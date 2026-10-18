<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One line of the stock ledger: a signed quantity (positive in, negative
 * out) at a unit cost, and the signed value it moved. `source` is the
 * document that moved the stock.
 *
 * @property int $id
 * @property int $item_id
 * @property int $warehouse_id
 * @property Carbon $date
 * @property StockMovementType $type
 * @property string $quantity
 * @property string $unit_cost
 * @property string $value
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $description
 * @property int|null $reversal_of_id
 * @property bool $is_reversed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Item $item
 * @property-read Warehouse $warehouse
 * @property-read Model|null $source
 */
#[Fillable([
    'item_id', 'warehouse_id', 'date', 'type', 'quantity', 'unit_cost', 'value',
    'source_type', 'source_id', 'description', 'reversal_of_id', 'is_reversed',
])]
class StockMovement extends Model
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

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => StockMovementType::class,
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'value' => 'decimal:4',
            'is_reversed' => 'boolean',
        ];
    }
}
