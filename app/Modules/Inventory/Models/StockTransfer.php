<?php

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Accounting\Enums\DocumentStatus;
use Database\Factories\StockTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Stock moved from one warehouse to another, at the average cost of the
 * sending warehouse. Both warehouses use the same inventory accounts, so a
 * transfer has no journal entry.
 *
 * @property int $id
 * @property string $number
 * @property Carbon $date
 * @property int $from_warehouse_id
 * @property int $to_warehouse_id
 * @property string|null $description
 * @property DocumentStatus $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Warehouse $fromWarehouse
 * @property-read Warehouse $toWarehouse
 * @property-read User|null $creator
 * @property-read Collection<int, StockTransferLine> $lines
 */
#[Fillable(['number', 'date', 'from_warehouse_id', 'to_warehouse_id', 'description', 'status', 'created_by'])]
#[UseFactory(StockTransferFactory::class)]
class StockTransfer extends Model
{
    /** @use HasFactory<StockTransferFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<StockTransferLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class)->orderBy('sort');
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => DocumentStatus::class,
        ];
    }
}
