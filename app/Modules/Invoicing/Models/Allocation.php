<?php

namespace App\Modules\Invoicing\Models;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use Database\Factories\AllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Part of an invoice settled by a payment or by a credit or debit note, in
 * the invoice's currency. `journal_entry` is the exchange difference posted
 * when the two were made at different rates.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int|null $payment_id
 * @property int|null $credit_note_id
 * @property string $amount
 * @property Carbon $date
 * @property int|null $journal_entry_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice $invoice
 * @property-read Payment|null $payment
 * @property-read Invoice|null $creditNote
 * @property-read JournalEntry|null $journalEntry
 * @property-read User|null $creator
 */
#[Fillable(['invoice_id', 'payment_id', 'credit_note_id', 'amount', 'date', 'journal_entry_id', 'created_by'])]
#[UseFactory(AllocationFactory::class)]
class Allocation extends Model
{
    /** @use HasFactory<AllocationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'credit_note_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'date' => 'date',
        ];
    }
}
