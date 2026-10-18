<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $stock_transfer_id
 * @property int $item_id
 * @property string $quantity
 * @property int $sort
 * @property-read Item $item
 */
#[Fillable(['stock_transfer_id', 'item_id', 'quantity', 'sort'])]
class StockTransferLine extends Model
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
        ];
    }
}
