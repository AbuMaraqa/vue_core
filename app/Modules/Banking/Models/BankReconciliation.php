<?php

namespace App\Modules\Banking\Models;

use App\Models\User;
use App\Modules\Accounting\Models\JournalLine;
use Database\Factories\BankReconciliationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A bank statement matched against the books: the journal lines it cleared
 * point to it through `reconciliation_id`.
 *
 * @property int $id
 * @property int $bank_account_id
 * @property Carbon $statement_date
 * @property string $statement_balance
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BankAccount $bankAccount
 * @property-read User|null $creator
 */
#[Fillable(['bank_account_id', 'statement_date', 'statement_balance', 'created_by'])]
#[UseFactory(BankReconciliationFactory::class)]
class BankReconciliation extends Model
{
    /** @use HasFactory<BankReconciliationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'reconciliation_id');
    }

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'statement_balance' => 'decimal:4',
        ];
    }
}
