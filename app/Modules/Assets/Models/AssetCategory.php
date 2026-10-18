<?php

namespace App\Modules\Assets\Models;

use App\Modules\Accounting\Models\Account;
use App\Modules\Assets\Enums\DepreciationMethod;
use App\Modules\Foundation\Concerns\HasTranslations;
use Database\Factories\AssetCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A class of assets (vehicles, computers...) with its three accounts and the
 * depreciation new assets of the class start with.
 *
 * @property int $id
 * @property array<string, string> $name
 * @property int $asset_account_id
 * @property int $accumulated_depreciation_account_id
 * @property int $depreciation_expense_account_id
 * @property DepreciationMethod $method
 * @property int $useful_life_months
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $assetAccount
 * @property-read Account $accumulatedDepreciationAccount
 * @property-read Account $depreciationExpenseAccount
 */
#[Fillable(['name', 'asset_account_id', 'accumulated_depreciation_account_id', 'depreciation_expense_account_id', 'method', 'useful_life_months'])]
#[UseFactory(AssetCategoryFactory::class)]
class AssetCategory extends Model
{
    /** @use HasFactory<AssetCategoryFactory> */
    use HasFactory, HasTranslations;

    /**
     * @return HasMany<Asset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'category_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accumulated_depreciation_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function depreciationExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_expense_account_id');
    }

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'method' => DepreciationMethod::class,
            'useful_life_months' => 'integer',
        ];
    }
}
