<?php

namespace App\Modules\Invoicing\Models;

use App\Models\User;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Invoicing\Enums\PaymentStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A sales invoice, credit note, purchase bill or debit note. Drafts can be
 * edited; posting numbers the document, posts its entry and moves its
 * stock; cancelling reverses both. `paid_amount` is what payments and notes
 * have settled of it (for a note: how much of it was applied).
 *
 * Opening invoices (`is_opening`) carry balances from a previous system:
 * one line against opening balance equity, no tax and no stock.
 *
 * @property int $id
 * @property InvoiceType $type
 * @property string|null $number
 * @property int $contact_id
 * @property Carbon $date
 * @property Carbon $due_date
 * @property string|null $reference
 * @property int $currency_id
 * @property string $exchange_rate
 * @property int|null $warehouse_id
 * @property int|null $cost_center_id
 * @property int|null $original_invoice_id
 * @property bool $prices_include_tax
 * @property bool $is_opening
 * @property string $subtotal
 * @property string $discount_total
 * @property string $tax_total
 * @property string $total
 * @property string $paid_amount
 * @property DocumentStatus $status
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $posted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Contact $contact
 * @property-read Currency $currency
 * @property-read Warehouse|null $warehouse
 * @property-read CostCenter|null $costCenter
 * @property-read Invoice|null $originalInvoice
 * @property-read User|null $creator
 * @property-read Collection<int, InvoiceLine> $lines
 * @property-read Collection<int, Allocation> $allocations
 * @property-read Collection<int, Allocation> $appliedTo
 */
#[Fillable([
    'type', 'number', 'contact_id', 'date', 'due_date', 'reference', 'currency_id', 'exchange_rate', 'warehouse_id',
    'cost_center_id', 'original_invoice_id', 'prices_include_tax', 'is_opening', 'subtotal', 'discount_total',
    'tax_total', 'total', 'paid_amount', 'status', 'notes', 'created_by', 'posted_at',
])]
#[UseFactory(InvoiceFactory::class)]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, LogsActivity;

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
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_invoice_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort');
    }

    /**
     * What settled this invoice: payments and applied notes.
     *
     * @return HasMany<Allocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    /**
     * For a note: the invoices it was applied to.
     *
     * @return HasMany<Allocation, $this>
     */
    public function appliedTo(): HasMany
    {
        return $this->hasMany(Allocation::class, 'credit_note_id');
    }

    /**
     * @return MorphMany<JournalEntry, $this>
     */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopePosted(Builder $query): void
    {
        $query->where('status', DocumentStatus::Posted);
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', DocumentStatus::Posted)->whereColumn('paid_amount', '<', 'total');
    }

    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    public function amountDue(): string
    {
        return Decimal::round(Decimal::of($this->total)->minus($this->paid_amount));
    }

    public function paymentStatus(): PaymentStatus
    {
        return match (true) {
            ! Decimal::isPositive($this->amountDue()) => PaymentStatus::Paid,
            Decimal::isPositive($this->paid_amount) => PaymentStatus::PartiallyPaid,
            default => PaymentStatus::Unpaid,
        };
    }

    public function isOverdue(): bool
    {
        return $this->status === DocumentStatus::Posted
            && Decimal::isPositive($this->amountDue())
            && $this->due_date->lessThan(now()->startOfDay());
    }

    /**
     * The number, or "Draft" before posting.
     */
    public function label(): string
    {
        return $this->number ?? __('Draft');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('invoices')
            ->logOnly(['number', 'type', 'contact_id', 'date', 'total', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'date' => 'date',
            'due_date' => 'date',
            'exchange_rate' => 'decimal:8',
            'prices_include_tax' => 'boolean',
            'is_opening' => 'boolean',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
            'paid_amount' => 'decimal:4',
            'status' => DocumentStatus::class,
            'posted_at' => 'datetime',
        ];
    }
}
