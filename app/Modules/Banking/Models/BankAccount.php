<?php

namespace App\Modules\Banking\Models;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Banking\Enums\BankAccountType;
use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A cash box or bank account. Its money is the balance of its own account
 * in the chart of accounts; in a foreign currency, that account only takes
 * amounts in the same currency, so the balance is known in both.
 *
 * @property int $id
 * @property string $name
 * @property BankAccountType $type
 * @property int $account_id
 * @property int $currency_id
 * @property string|null $bank_name
 * @property string|null $branch
 * @property string|null $account_number
 * @property string|null $iban
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Currency $currency
 */
#[Fillable(['name', 'type', 'account_id', 'currency_id', 'bank_name', 'branch', 'account_number', 'iban', 'is_active'])]
#[UseFactory(BankAccountFactory::class)]
class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return HasMany<BankReconciliation, $this>
     */
    public function reconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class);
    }

    /**
     * @return HasMany<ChequeBook, $this>
     */
    public function chequeBooks(): HasMany
    {
        return $this->hasMany(ChequeBook::class);
    }

    public function label(): string
    {
        return "{$this->name} ({$this->currency->code})";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('bank-accounts')
            ->logOnly(['name', 'type', 'bank_name', 'account_number', 'iban', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'type' => BankAccountType::class,
            'is_active' => 'boolean',
        ];
    }
}
