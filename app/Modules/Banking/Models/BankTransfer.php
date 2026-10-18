<?php

namespace App\Modules\Banking\Models;

use App\Models\User;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Models\JournalEntry;
use Database\Factories\BankTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Money moved between two of the company's accounts. `amount` leaves in the
 * sending currency and `to_amount` arrives in the receiving one; `fees` are
 * charged by the sending bank.
 *
 * @property int $id
 * @property string $number
 * @property Carbon $date
 * @property int $from_bank_account_id
 * @property int $to_bank_account_id
 * @property string $amount
 * @property string $to_amount
 * @property string $fees
 * @property string|null $reference
 * @property string|null $description
 * @property DocumentStatus $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BankAccount $fromAccount
 * @property-read BankAccount $toAccount
 * @property-read User|null $creator
 */
#[Fillable(['number', 'date', 'from_bank_account_id', 'to_bank_account_id', 'amount', 'to_amount', 'fees', 'reference', 'description', 'status', 'created_by'])]
#[UseFactory(BankTransferFactory::class)]
class BankTransfer extends Model
{
    /** @use HasFactory<BankTransferFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'from_bank_account_id');
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'to_bank_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return MorphMany<JournalEntry, $this>
     */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:4',
            'to_amount' => 'decimal:4',
            'fees' => 'decimal:4',
            'status' => DocumentStatus::class,
        ];
    }
}
