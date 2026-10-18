<?php

namespace App\Modules\Invoicing\Models;

use App\Models\User;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\Cheque;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Invoicing\Enums\PaymentMethod;
use App\Modules\Invoicing\Enums\PaymentType;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Money received from a customer or paid to a vendor, allocated to their
 * invoices. What is not allocated stays as credit on the contact. A payment
 * by a cheque that bounced or was cancelled no longer settles anything.
 *
 * @property int $id
 * @property PaymentType $type
 * @property string $number
 * @property int $contact_id
 * @property Carbon $date
 * @property PaymentMethod $method
 * @property int|null $bank_account_id
 * @property int|null $cheque_id
 * @property string $amount
 * @property int $currency_id
 * @property string $exchange_rate
 * @property string|null $reference
 * @property string|null $description
 * @property DocumentStatus $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Contact $contact
 * @property-read BankAccount|null $bankAccount
 * @property-read Cheque|null $cheque
 * @property-read Currency $currency
 * @property-read User|null $creator
 * @property-read Collection<int, Allocation> $allocations
 */
#[Fillable([
    'type', 'number', 'contact_id', 'date', 'method', 'bank_account_id', 'cheque_id', 'amount', 'currency_id',
    'exchange_rate', 'reference', 'description', 'status', 'created_by',
])]
#[UseFactory(PaymentFactory::class)]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<Cheque, $this>
     */
    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Allocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    /**
     * @return MorphMany<JournalEntry, $this>
     */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    /**
     * Whether it still settles invoices: posted, and not paid by a cheque
     * that bounced or was cancelled.
     */
    public function isEffective(): bool
    {
        return $this->status === DocumentStatus::Posted && ! ($this->cheque?->status->isVoid() ?? false);
    }

    public function allocatedAmount(): string
    {
        return Decimal::round(Decimal::sum($this->allocations->pluck('amount')));
    }

    /**
     * What is left to allocate.
     */
    public function unallocatedAmount(): string
    {
        return $this->isEffective() ? Decimal::round(Decimal::of($this->amount)->minus($this->allocatedAmount())) : '0.0000';
    }

    protected function casts(): array
    {
        return [
            'type' => PaymentType::class,
            'date' => 'date',
            'method' => PaymentMethod::class,
            'amount' => 'decimal:4',
            'exchange_rate' => 'decimal:8',
            'status' => DocumentStatus::class,
        ];
    }
}
