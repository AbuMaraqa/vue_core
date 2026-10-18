<?php

namespace App\Modules\Production\Models;

use App\Modules\Accounting\Support\Decimal;
use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A material of a recipe: the net quantity a batch needs, and the share that
 * is usually wasted (offcuts, sawdust...), which is issued on top of it.
 *
 * @property int $id
 * @property int $bill_of_materials_id
 * @property int $item_id
 * @property string $quantity
 * @property string $waste_percent
 * @property int $sort
 * @property-read Item $item
 */
#[Fillable(['bill_of_materials_id', 'item_id', 'quantity', 'waste_percent', 'sort'])]
class BillOfMaterialsComponent extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * The quantity a batch takes from stock, waste included.
     */
    public function grossQuantity(): string
    {
        $quantity = Decimal::of($this->quantity);

        return Decimal::round($quantity->plus(Decimal::percentOf($quantity, $this->waste_percent)));
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'waste_percent' => 'decimal:4',
        ];
    }
}
