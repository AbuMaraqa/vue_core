<?php

namespace App\Modules\Assets\Models;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Enums\DepreciationMethod;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A fixed asset. It is depreciated monthly from `depreciation_start_date`
 * over `useful_life_months`, down to its salvage value.
 *
 * An asset carried over from a previous system starts with the depreciation
 * already taken (`opening_depreciation`) and the months of life it has left.
 * `accumulated_depreciation` includes it; `depreciated_until` is the last
 * month-end posted.
 *
 * @property int $id
 * @property string $number
 * @property string $name
 * @property int $category_id
 * @property string|null $serial_number
 * @property string|null $location
 * @property int|null $cost_center_id
 * @property Carbon $acquisition_date
 * @property Carbon $depreciation_start_date
 * @property string $cost
 * @property string $salvage_value
 * @property DepreciationMethod $method
 * @property int $useful_life_months
 * @property string|null $declining_rate Yearly percent, e.g. `40.0000`.
 * @property string $opening_depreciation
 * @property string $accumulated_depreciation
 * @property Carbon|null $depreciated_until
 * @property int|null $funding_account_id
 * @property AssetStatus $status
 * @property Carbon|null $disposal_date
 * @property string|null $disposal_proceeds
 * @property int|null $disposal_account_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AssetCategory $category
 * @property-read CostCenter|null $costCenter
 * @property-read Account|null $fundingAccount
 * @property-read User|null $creator
 */
#[Fillable([
    'number', 'name', 'category_id', 'serial_number', 'location', 'cost_center_id', 'acquisition_date',
    'depreciation_start_date', 'cost', 'salvage_value', 'method', 'useful_life_months', 'declining_rate',
    'opening_depreciation', 'accumulated_depreciation', 'depreciated_until', 'funding_account_id', 'status',
    'disposal_date', 'disposal_proceeds', 'disposal_account_id', 'notes', 'created_by',
])]
#[UseFactory(AssetFactory::class)]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return BelongsTo<AssetCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function fundingAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'funding_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AssetDepreciation, $this>
     */
    public function depreciations(): HasMany
    {
        return $this->hasMany(AssetDepreciation::class)->orderBy('to_date');
    }

    /**
     * @return MorphMany<JournalEntry, $this>
     */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function bookValue(): string
    {
        return Decimal::round(Decimal::of($this->cost)->minus($this->accumulated_depreciation));
    }

    /**
     * What may still be depreciated: down to the salvage value.
     */
    public function depreciableRemaining(): string
    {
        return Decimal::round(Decimal::of($this->cost)->minus($this->salvage_value)->minus($this->accumulated_depreciation));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('assets')
            ->logOnly(['number', 'name', 'location', 'status', 'cost_center_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'depreciation_start_date' => 'date',
            'cost' => 'decimal:4',
            'salvage_value' => 'decimal:4',
            'method' => DepreciationMethod::class,
            'useful_life_months' => 'integer',
            'declining_rate' => 'decimal:4',
            'opening_depreciation' => 'decimal:4',
            'accumulated_depreciation' => 'decimal:4',
            'depreciated_until' => 'date',
            'status' => AssetStatus::class,
            'disposal_date' => 'date',
            'disposal_proceeds' => 'decimal:4',
        ];
    }
}
