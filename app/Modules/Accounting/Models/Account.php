<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Foundation\Concerns\HasTranslations;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An account of the chart of accounts.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $code
 * @property array<string, string> $name
 * @property AccountType $type
 * @property AccountSubtype $subtype
 * @property bool $is_group
 * @property bool $is_active
 * @property int|null $currency_id
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account|null $parent
 * @property-read Currency|null $currency
 */
#[Fillable(['parent_id', 'code', 'name', 'type', 'subtype', 'is_group', 'is_active', 'currency_id', 'description'])]
#[UseFactory(AccountFactory::class)]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory, HasTranslations, LogsActivity;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * Accounts that can receive postings.
     *
     * @param  Builder<Account>  $query
     */
    public function scopePostable(Builder $query): void
    {
        $query->where('is_group', false)->where('is_active', true);
    }

    /**
     * `1101 - Cash`, for selects and reports.
     */
    public function label(): string
    {
        return "{$this->code} - {$this->translate('name')}";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('accounts')
            ->logOnly(['code', 'name', 'type', 'subtype', 'is_group', 'is_active', 'parent_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'type' => AccountType::class,
            'subtype' => AccountSubtype::class,
            'is_group' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
