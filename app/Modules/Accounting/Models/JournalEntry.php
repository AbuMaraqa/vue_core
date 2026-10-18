<?php

namespace App\Modules\Accounting\Models;

use App\Models\User;
use App\Modules\Accounting\Enums\JournalEntryStatus;
use Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A journal entry: balanced debits and credits on a date.
 *
 * Entries created by documents (invoices, payments...) point back to them
 * through `source`; manual entries have none.
 *
 * @property int $id
 * @property string|null $number
 * @property Carbon $date
 * @property int|null $fiscal_year_id
 * @property string|null $reference
 * @property string|null $description
 * @property JournalEntryStatus $status
 * @property int $currency_id
 * @property string $exchange_rate
 * @property string $total
 * @property string $base_total
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $reversal_of_id
 * @property Carbon|null $posted_at
 * @property int|null $posted_by
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Currency $currency
 * @property-read FiscalYear|null $fiscalYear
 * @property-read JournalEntry|null $reversalOf
 * @property-read JournalEntry|null $reversedBy
 * @property-read Model|null $source
 * @property-read User|null $creator
 * @property-read User|null $poster
 * @property-read Collection<int, JournalLine> $lines
 */
#[Fillable([
    'number', 'date', 'fiscal_year_id', 'reference', 'description', 'status', 'currency_id', 'exchange_rate',
    'total', 'base_total', 'source_type', 'source_id', 'reversal_of_id', 'posted_at', 'posted_by', 'created_by',
])]
#[UseFactory(JournalEntryFactory::class)]
class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory;

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('sort');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return BelongsTo<FiscalYear, $this>
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /**
     * @return HasOne<JournalEntry, $this>
     */
    public function reversedBy(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * Entries that are part of the books. A reversed entry stays in the books
     * together with the entry that reverses it.
     *
     * @param  Builder<JournalEntry>  $query
     */
    public function scopeInBooks(Builder $query): void
    {
        $query->whereIn('status', [JournalEntryStatus::Posted, JournalEntryStatus::Reversed]);
    }

    public function isDraft(): bool
    {
        return $this->status === JournalEntryStatus::Draft;
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => JournalEntryStatus::class,
            'exchange_rate' => 'decimal:8',
            'total' => 'decimal:4',
            'base_total' => 'decimal:4',
            'posted_at' => 'datetime',
        ];
    }
}
