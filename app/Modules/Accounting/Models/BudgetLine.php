<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The planned amount of an account in one month (1–12) of the fiscal year.
 *
 * @property int $id
 * @property int $budget_id
 * @property int $account_id
 * @property int $month
 * @property string $amount
 * @property-read Account $account
 */
#[Fillable(['budget_id', 'account_id', 'month', 'amount'])]
class BudgetLine extends Model
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
            'month' => 'integer',
            'amount' => 'decimal:4',
        ];
    }
}
