<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Accounting\Models\Account;
use App\Modules\Foundation\Concerns\HasTranslations;
use App\Modules\Payroll\Enums\PayCalculation;
use App\Modules\Payroll\Enums\PayComponentType;
use Database\Factories\PayComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An allowance, deduction or employer contribution, e.g. "Transport
 * allowance", "Social security 7.5%". `account` is the expense of an
 * earning or contribution, or where a deduction goes (a liability, or the
 * employee advances being recovered); a contribution is also owed to
 * `liability_account`.
 *
 * @property int $id
 * @property array<string, string> $name
 * @property PayComponentType $type
 * @property PayCalculation $calculation
 * @property string $default_value Amount, or percent of basic.
 * @property int $account_id
 * @property int|null $liability_account_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Account|null $liabilityAccount
 */
#[Fillable(['name', 'type', 'calculation', 'default_value', 'account_id', 'liability_account_id', 'is_active'])]
#[UseFactory(PayComponentFactory::class)]
class PayComponent extends Model
{
    /** @use HasFactory<PayComponentFactory> */
    use HasFactory, HasTranslations;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function liabilityAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'liability_account_id');
    }

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'type' => PayComponentType::class,
            'calculation' => PayCalculation::class,
            'default_value' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }
}
