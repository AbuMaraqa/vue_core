<?php

namespace App\Modules\Banking\Models;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Banking\Enums\ChequeAction;
use App\Modules\Banking\Enums\ChequeDirection;
use App\Modules\Banking\Enums\ChequeStatus;
use App\Modules\Contacts\Models\Contact;
use Database\Factories\ChequeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A cheque received from a customer or written to a vendor, followed until
 * the bank pays it. Its amount stays in its own currency at the rate of the
 * day it was received or issued. `counter_account` is the account it
 * settled (usually the contact's receivable or payable); it is restored when
 * the cheque bounces or is cancelled.
 *
 * @property int $id
 * @property ChequeDirection $direction
 * @property string $number
 * @property ChequeStatus $status
 * @property int|null $contact_id
 * @property int|null $bank_account_id
 * @property int|null $cheque_book_id
 * @property string|null $drawee_bank
 * @property string $amount
 * @property int $currency_id
 * @property string $exchange_rate
 * @property Carbon $issue_date
 * @property Carbon $due_date
 * @property int $counter_account_id
 * @property int|null $endorsed_to_contact_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $description
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Contact|null $contact
 * @property-read BankAccount|null $bankAccount
 * @property-read ChequeBook|null $chequeBook
 * @property-read Currency $currency
 * @property-read Account $counterAccount
 * @property-read Contact|null $endorsedTo
 * @property-read Model|null $source
 * @property-read User|null $creator
 * @property-read Collection<int, ChequeEvent> $events
 */
#[Fillable([
    'direction', 'number', 'status', 'contact_id', 'bank_account_id', 'cheque_book_id', 'drawee_bank', 'amount',
    'currency_id', 'exchange_rate', 'issue_date', 'due_date', 'counter_account_id', 'endorsed_to_contact_id',
    'source_type', 'source_id', 'description', 'created_by',
])]
#[UseFactory(ChequeFactory::class)]
class Cheque extends Model
{
    /** @use HasFactory<ChequeFactory> */
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
     * @return BelongsTo<ChequeBook, $this>
     */
    public function chequeBook(): BelongsTo
    {
        return $this->belongsTo(ChequeBook::class);
    }

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
    public function counterAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'counter_account_id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function endorsedTo(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'endorsed_to_contact_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ChequeEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ChequeEvent::class)->orderBy('id');
    }

    /**
     * @param  Builder<Cheque>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', ChequeStatus::open());
    }

    /**
     * @return list<ChequeAction>
     */
    public function availableActions(): array
    {
        return ChequeAction::availableFor($this->direction, $this->status);
    }

    protected function casts(): array
    {
        return [
            'direction' => ChequeDirection::class,
            'status' => ChequeStatus::class,
            'amount' => 'decimal:4',
            'exchange_rate' => 'decimal:8',
            'issue_date' => 'date',
            'due_date' => 'date',
        ];
    }
}
