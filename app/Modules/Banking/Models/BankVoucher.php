<?php

namespace App\Modules\Banking\Models;

use App\Models\User;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Banking\Enums\VoucherDirection;
use App\Modules\Contacts\Models\Contact;
use Database\Factories\BankVoucherFactory;
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
 * A payment voucher (money out: rent, utilities, petty cash) or a receipt
 * voucher (money in: interest, a refund) on a bank or cash account, split
 * over accounts, with tax. Customer and vendor payments for invoices are
 * made in the Invoicing module instead.
 *
 * @property int $id
 * @property string $number
 * @property VoucherDirection $direction
 * @property Carbon $date
 * @property int $bank_account_id
 * @property int|null $contact_id
 * @property string|null $payee
 * @property int $currency_id
 * @property string $exchange_rate
 * @property string|null $reference
 * @property string|null $description
 * @property string $subtotal
 * @property string $tax_total
 * @property string $total
 * @property DocumentStatus $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BankAccount $bankAccount
 * @property-read Contact|null $contact
 * @property-read Currency $currency
 * @property-read User|null $creator
 * @property-read Collection<int, BankVoucherLine> $lines
 */
#[Fillable([
    'number', 'direction', 'date', 'bank_account_id', 'contact_id', 'payee', 'currency_id', 'exchange_rate',
    'reference', 'description', 'subtotal', 'tax_total', 'total', 'status', 'created_by',
])]
#[UseFactory(BankVoucherFactory::class)]
class BankVoucher extends Model
{
    /** @use HasFactory<BankVoucherFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
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
     * @return HasMany<BankVoucherLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BankVoucherLine::class)->orderBy('sort');
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
            'direction' => VoucherDirection::class,
            'date' => 'date',
            'exchange_rate' => 'decimal:8',
            'subtotal' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
            'status' => DocumentStatus::class,
        ];
    }
}
