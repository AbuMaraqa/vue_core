<?php

namespace App\Modules\Banking\Models;

use Database\Factories\ChequeBookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A book of blank cheques of one bank account, numbered from `first_number`
 * to `last_number`. Issued cheques take `next_number`.
 *
 * @property int $id
 * @property int $bank_account_id
 * @property int $first_number
 * @property int $last_number
 * @property int $next_number
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BankAccount $bankAccount
 */
#[Fillable(['bank_account_id', 'first_number', 'last_number', 'next_number', 'is_active'])]
#[UseFactory(ChequeBookFactory::class)]
class ChequeBook extends Model
{
    /** @use HasFactory<ChequeBookFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return HasMany<Cheque, $this>
     */
    public function cheques(): HasMany
    {
        return $this->hasMany(Cheque::class);
    }

    public function isUsedUp(): bool
    {
        return $this->next_number > $this->last_number;
    }

    public function remaining(): int
    {
        return max(0, $this->last_number - $this->next_number + 1);
    }

    protected function casts(): array
    {
        return [
            'first_number' => 'integer',
            'last_number' => 'integer',
            'next_number' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
