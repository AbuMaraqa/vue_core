<?php

namespace App\Modules\Contacts\Models;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Contacts\Enums\ContactKind;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A customer, a vendor, or both. Its balance is read from the journal lines
 * that name it as their partner on receivable and payable accounts.
 *
 * @property int $id
 * @property string $code
 * @property ContactKind $kind
 * @property string $name
 * @property string|null $contact_person
 * @property bool $is_customer
 * @property bool $is_vendor
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $mobile
 * @property string|null $tax_number
 * @property int|null $currency_id
 * @property int|null $receivable_account_id
 * @property int|null $payable_account_id
 * @property int $payment_terms_days
 * @property string|null $credit_limit
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Currency|null $currency
 * @property-read Account|null $receivableAccount
 * @property-read Account|null $payableAccount
 * @property-read bool|null $journal_lines_exists
 */
#[Fillable([
    'code', 'kind', 'name', 'contact_person', 'is_customer', 'is_vendor', 'email', 'phone', 'mobile',
    'tax_number', 'currency_id', 'receivable_account_id', 'payable_account_id', 'payment_terms_days',
    'credit_limit', 'address', 'city', 'country', 'notes', 'is_active',
])]
#[UseFactory(ContactFactory::class)]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'receivable_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payable_account_id');
    }

    /**
     * @return MorphMany<JournalLine, $this>
     */
    public function journalLines(): MorphMany
    {
        return $this->morphMany(JournalLine::class, 'partner');
    }

    /**
     * @param  Builder<Contact>  $query
     */
    public function scopeCustomers(Builder $query): void
    {
        $query->where('is_customer', true);
    }

    /**
     * @param  Builder<Contact>  $query
     */
    public function scopeVendors(Builder $query): void
    {
        $query->where('is_vendor', true);
    }

    /**
     * The account the contact's sales are owed on: its own, else the default.
     */
    public function receivableAccountId(): int
    {
        return $this->receivable_account_id ?? app(DefaultAccounts::class)->id(AccountRole::Receivable);
    }

    /**
     * The account the contact's purchases are owed on: its own, else the default.
     */
    public function payableAccountId(): int
    {
        return $this->payable_account_id ?? app(DefaultAccounts::class)->id(AccountRole::Payable);
    }

    public function label(): string
    {
        return "{$this->code} - {$this->name}";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('contacts')
            ->logOnly([
                'code', 'name', 'is_customer', 'is_vendor', 'tax_number', 'currency_id', 'receivable_account_id',
                'payable_account_id', 'payment_terms_days', 'credit_limit', 'is_active',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'kind' => ContactKind::class,
            'is_customer' => 'boolean',
            'is_vendor' => 'boolean',
            'payment_terms_days' => 'integer',
            'credit_limit' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }
}
