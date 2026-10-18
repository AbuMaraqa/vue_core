<?php

namespace App\Modules\Invoicing\Models;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an invoice. `subtotal` is after discount and before tax;
 * `unit_cost` is the stock cost of a unit, recorded when the line's stock
 * moved.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int|null $item_id
 * @property string|null $description
 * @property int $account_id
 * @property int|null $cost_center_id
 * @property string $quantity
 * @property string $unit_price
 * @property string $discount_percent
 * @property int|null $tax_id
 * @property string $tax_rate
 * @property string $discount_amount
 * @property string $subtotal
 * @property string $tax_amount
 * @property string $total
 * @property string|null $unit_cost
 * @property int $sort
 * @property-read Invoice $invoice
 * @property-read Item|null $item
 * @property-read Account $account
 * @property-read CostCenter|null $costCenter
 * @property-read Tax|null $tax
 */
#[Fillable([
    'invoice_id', 'item_id', 'description', 'account_id', 'cost_center_id', 'quantity', 'unit_price',
    'discount_percent', 'tax_id', 'tax_rate', 'discount_amount', 'subtotal', 'tax_amount', 'total', 'unit_cost', 'sort',
])]
class InvoiceLine extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
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
            'unit_cost' => 'decimal:6',
        ];
    }
}
