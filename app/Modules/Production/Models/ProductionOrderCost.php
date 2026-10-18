<?php

namespace App\Modules\Production\Models;

use App\Modules\Accounting\Models\Account;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cost of the order other than materials, e.g. the carpenters' labour or
 * the painting, added to the product's cost and credited to `account` when
 * the order completes.
 *
 * @property int $id
 * @property int $production_order_id
 * @property string $description
 * @property int $account_id
 * @property string $amount
 * @property int $sort
 * @property-read Account $account
 */
#[Fillable(['production_order_id', 'description', 'account_id', 'amount', 'sort'])]
class ProductionOrderCost extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
        ];
    }
}
