<?php

namespace App\Modules\Banking\Models;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\Tax;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One account of a voucher: the amount before tax and the tax on it.
 *
 * @property int $id
 * @property int $bank_voucher_id
 * @property int $account_id
 * @property int|null $cost_center_id
 * @property int|null $tax_id
 * @property string|null $description
 * @property string $amount
 * @property string $tax_amount
 * @property int $sort
 * @property-read Account $account
 * @property-read CostCenter|null $costCenter
 * @property-read Tax|null $tax
 */
#[Fillable(['bank_voucher_id', 'account_id', 'cost_center_id', 'tax_id', 'description', 'amount', 'tax_amount', 'sort'])]
class BankVoucherLine extends Model
{
    public $timestamps = false;

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
            'amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
        ];
    }
}
