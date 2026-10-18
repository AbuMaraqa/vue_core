<?php

namespace App\Modules\Production\Models;

use App\Modules\Inventory\Models\Item;
use Database\Factories\BillOfMaterialsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The recipe of a product: which materials, and how much of each, make
 * `quantity` units of the item, plus the other costs of one batch (labour,
 * machines, power...). A production order copies it, scaled to the quantity
 * to make, and may then be adjusted.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $item_id
 * @property string $quantity
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Item $item
 * @property-read Collection<int, BillOfMaterialsComponent> $components
 * @property-read Collection<int, BillOfMaterialsCost> $costs
 */
#[Fillable(['code', 'name', 'item_id', 'quantity', 'notes', 'is_active'])]
#[Table('bills_of_materials')]
#[UseFactory(BillOfMaterialsFactory::class)]
class BillOfMaterials extends Model
{
    /** @use HasFactory<BillOfMaterialsFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return HasMany<BillOfMaterialsComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(BillOfMaterialsComponent::class, 'bill_of_materials_id')->orderBy('sort');
    }

    /**
     * @return HasMany<BillOfMaterialsCost, $this>
     */
    public function costs(): HasMany
    {
        return $this->hasMany(BillOfMaterialsCost::class, 'bill_of_materials_id')->orderBy('sort');
    }

    public function label(): string
    {
        return "{$this->code} - {$this->name}";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('bills_of_materials')
            ->logOnly(['code', 'name', 'item_id', 'quantity', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }
}
