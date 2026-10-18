<?php

namespace App\Modules\Production\Models;

use App\Modules\Accounting\Models\Account;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cost of one batch other than materials, e.g. labour or power, credited
 * to `account` when an order completes (applied production costs by
 * default).
 *
 * @property int $id
 * @property int $bill_of_materials_id
 * @property string $description
 * @property int $account_id
 * @property string $amount
 * @property int $sort
 * @property-read Account $account
 */
#[Fillable(['bill_of_materials_id', 'description', 'account_id', 'amount', 'sort'])]
class BillOfMaterialsCost extends Model
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
