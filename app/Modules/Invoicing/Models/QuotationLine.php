<?php

namespace App\Modules\Invoicing\Models;

use App\Modules\Accounting\Models\Tax;
use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a quotation, worked out like an invoice line. `subtotal` is
 * after discount and before tax.
 *
 * @property int $id
 * @property int $quotation_id
 * @property int|null $item_id
 * @property string|null $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $discount_percent
 * @property int|null $tax_id
 * @property string $tax_rate
 * @property string $discount_amount
 * @property string $subtotal
 * @property string $tax_amount
 * @property string $total
 * @property int $sort
 * @property-read Quotation $quotation
 * @property-read Item|null $item
 * @property-read Tax|null $tax
 */
#[Fillable([
    'quotation_id', 'item_id', 'description', 'quantity', 'unit_price', 'discount_percent', 'tax_id', 'tax_rate',
    'discount_amount', 'subtotal', 'tax_amount', 'total', 'sort',
])]
class QuotationLine extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<Tax, $this>
     */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:6',
            'discount_percent' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total' => 'decimal:4',
        ];
    }
}
