<?php

namespace App\Modules\Invoicing\Models;

use App\Models\User;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Invoicing\Enums\QuotationStatus;
use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A sales quotation (price offer) given to a customer. It has no effect on
 * the books or the stock; once the customer agrees it is converted to a
 * draft sales invoice, which is then posted like any other.
 *
 * Its number is taken when it is first saved, because the customer receives
 * it straight away. Deleting the invoice it became frees it again.
 *
 * @property int $id
 * @property string $number
 * @property int $contact_id
 * @property Carbon $date
 * @property Carbon $valid_until
 * @property string|null $reference
 * @property int $currency_id
 * @property bool $prices_include_tax
 * @property string $subtotal
 * @property string $discount_total
 * @property string $tax_total
 * @property string $total
 * @property QuotationStatus $status
 * @property string|null $notes
 * @property int|null $invoice_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Contact $contact
 * @property-read Currency $currency
 * @property-read Invoice|null $invoice
 * @property-read User|null $creator
 * @property-read Collection<int, QuotationLine> $lines
 */
#[Fillable([
    'number', 'contact_id', 'date', 'valid_until', 'reference', 'currency_id', 'prices_include_tax', 'subtotal',
    'discount_total', 'tax_total', 'total', 'status', 'notes', 'invoice_id', 'created_by',
])]
#[UseFactory(QuotationFactory::class)]
class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
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
     * The sales invoice it was converted to.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<QuotationLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class)->orderBy('sort');
    }

    /**
     * Limit a query of quotations to a status as the user sees it, including
     * the worked-out invoiced and expired ones. Static rather than a scope,
     * so it also applies to the untyped builders of QueryBuilder filters.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function whereDisplayStatus(Builder $query, QuotationStatus $status): void
    {
        match ($status) {
            QuotationStatus::Invoiced => $query->whereNotNull('invoice_id'),
            QuotationStatus::Expired => $query->whereNull('invoice_id')
                ->whereIn('status', [QuotationStatus::Draft, QuotationStatus::Sent])
                ->whereDate('valid_until', '<', now()->toDateString()),
            QuotationStatus::Draft, QuotationStatus::Sent => $query->whereNull('invoice_id')
                ->where('status', $status)
                ->whereDate('valid_until', '>=', now()->toDateString()),
            default => $query->whereNull('invoice_id')->where('status', $status),
        };
    }

    public function isInvoiced(): bool
    {
        return $this->invoice_id !== null;
    }

    /**
     * Still waiting for the customer after the last day it is valid.
     */
    public function isExpired(): bool
    {
        return ! $this->isInvoiced()
            && in_array($this->status, [QuotationStatus::Draft, QuotationStatus::Sent], true)
            && $this->valid_until->lessThan(now()->startOfDay());
    }

    /**
     * The status shown to the user.
     */
    public function displayStatus(): QuotationStatus
    {
        return match (true) {
            $this->isInvoiced() => QuotationStatus::Invoiced,
            $this->isExpired() => QuotationStatus::Expired,
            default => $this->status,
        };
    }

    /**
     * Its content can change while the customer has not answered yet.
     */
    public function isEditable(): bool
    {
        return ! $this->isInvoiced() && in_array($this->status, [QuotationStatus::Draft, QuotationStatus::Sent], true);
    }

    /**
     * Any open quotation can be invoiced, even an expired one the customer
     * agreed to late; a rejected one must be offered again first.
     */
    public function isConvertible(): bool
    {
        return ! $this->isInvoiced() && $this->status !== QuotationStatus::Rejected;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('quotations')
            ->logOnly(['number', 'contact_id', 'date', 'valid_until', 'total', 'status', 'invoice_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'valid_until' => 'date',
            'prices_include_tax' => 'boolean',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
            'status' => QuotationStatus::class,
        ];
    }
}
