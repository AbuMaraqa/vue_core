<?php

namespace App\Modules\Assets\Models;

use App\Models\User;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Models\JournalEntry;
use Database\Factories\DepreciationRunFactory;
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
 * The depreciation of every asset up to a month-end, posted in one entry.
 *
 * @property int $id
 * @property string $number
 * @property Carbon $period_end
 * @property string $total
 * @property DocumentStatus $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Collection<int, AssetDepreciation> $depreciations
 */
#[Fillable(['number', 'period_end', 'total', 'status', 'created_by'])]
#[UseFactory(DepreciationRunFactory::class)]
class DepreciationRun extends Model
{
    /** @use HasFactory<DepreciationRunFactory> */
    use HasFactory;

    /**
     * @return HasMany<AssetDepreciation, $this>
     */
    public function depreciations(): HasMany
    {
        return $this->hasMany(AssetDepreciation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
            'period_end' => 'date',
            'total' => 'decimal:4',
            'status' => DocumentStatus::class,
        ];
    }
}
