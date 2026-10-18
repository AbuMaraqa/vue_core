<?php

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use Database\Factories\StockAdjustmentFactory;
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
 * A correction of stock in one warehouse: a count, damage, loss, or the
 * opening stock. Increases and decreases are valued and posted against
 * `account` (inventory adjustments, or opening balance equity for opening
 * stock).
 *
 * @property int $id
 * @property string $number
 * @property Carbon $date
 * @property int $warehouse_id
 * @property int $account_id
 * @property string|null $description
 * @property DocumentStatus $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Warehouse $warehouse
 * @property-read Account $account
 * @property-read User|null $creator
 * @property-read Collection<int, StockAdjustmentLine> $lines
 */
#[Fillable(['number', 'date', 'warehouse_id', 'account_id', 'description', 'status', 'created_by'])]
#[UseFactory(StockAdjustmentFactory::class)]
class StockAdjustment extends Model
{
    /** @use HasFactory<StockAdjustmentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<StockAdjustmentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockAdjustmentLine::class)->orderBy('sort');
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
            'date' => 'date',
            'status' => DocumentStatus::class,
        ];
    }
}
