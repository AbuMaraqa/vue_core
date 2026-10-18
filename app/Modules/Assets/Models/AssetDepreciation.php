<?php

namespace App\Modules\Assets\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The depreciation one asset took in a run (or when it was disposed), for
 * the months from `from_date` to `to_date`.
 *
 * @property int $id
 * @property int $asset_id
 * @property int|null $depreciation_run_id
 * @property Carbon $from_date
 * @property Carbon $to_date
 * @property int $months
 * @property string $amount
 * @property Carbon|null $previous_depreciated_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Asset $asset
 * @property-read DepreciationRun|null $run
 */
#[Fillable(['asset_id', 'depreciation_run_id', 'from_date', 'to_date', 'months', 'amount', 'previous_depreciated_until'])]
class AssetDepreciation extends Model
{
    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<DepreciationRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(DepreciationRun::class, 'depreciation_run_id');
    }

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'months' => 'integer',
            'amount' => 'decimal:4',
            'previous_depreciated_until' => 'date',
        ];
    }
}
