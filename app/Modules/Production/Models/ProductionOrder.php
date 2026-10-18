<?php

namespace App\Modules\Production\Models;

use App\Models\User;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Production\Enums\ProductionOrderStatus;
use Database\Factories\ProductionOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An order to make `quantity` units of an item from materials taken from
 * `materialsWarehouse`, e.g. ten tables for a customer. The finished
 * product goes into `productWarehouse` at the cost of what was consumed plus
 * the other costs, so its unit cost is real, not estimated.
 *
 * The cost figures are filled when the order completes.
 *
 * @property int $id
 * @property string $number
 * @property Carbon $date
 * @property int|null $bill_of_materials_id
 * @property int $item_id
 * @property string $quantity
 * @property string|null $produced_quantity
 * @property int $materials_warehouse_id
 * @property int $product_warehouse_id
 * @property int|null $contact_id
 * @property int|null $cost_center_id
 * @property string|null $reference
 * @property string|null $description
 * @property ProductionOrderStatus $status
 * @property Carbon|null $started_on
 * @property Carbon|null $completed_on
 * @property string $materials_cost
 * @property string $other_costs
 * @property string $total_cost
 * @property string $unit_cost
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BillOfMaterials|null $billOfMaterials
 * @property-read Item $item
 * @property-read Warehouse $materialsWarehouse
 * @property-read Warehouse $productWarehouse
 * @property-read Contact|null $contact
 * @property-read CostCenter|null $costCenter
 * @property-read User|null $creator
 * @property-read Collection<int, ProductionOrderMaterial> $materials
 * @property-read Collection<int, ProductionOrderCost> $costs
 * @property-read Collection<int, JournalEntry> $journalEntries
 */
#[Fillable([
    'number', 'date', 'bill_of_materials_id', 'item_id', 'quantity', 'produced_quantity', 'materials_warehouse_id',
    'product_warehouse_id', 'contact_id', 'cost_center_id', 'reference', 'description', 'status', 'started_on',
    'completed_on', 'materials_cost', 'other_costs', 'total_cost', 'unit_cost', 'created_by',
])]
#[UseFactory(ProductionOrderFactory::class)]
class ProductionOrder extends Model
{
    /** @use HasFactory<ProductionOrderFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return BelongsTo<BillOfMaterials, $this>
     */
    public function billOfMaterials(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterials::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function materialsWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'materials_warehouse_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function productWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'product_warehouse_id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ProductionOrderMaterial, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(ProductionOrderMaterial::class)->orderBy('sort');
    }

    /**
     * @return HasMany<ProductionOrderCost, $this>
     */
    public function costs(): HasMany
    {
        return $this->hasMany(ProductionOrderCost::class)->orderBy('sort');
    }

    /**
     * @return MorphMany<JournalEntry, $this>
     */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    /**
     * @return MorphMany<StockMovement, $this>
     */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('production_orders')
            ->logOnly(['number', 'item_id', 'quantity', 'produced_quantity', 'status', 'total_cost'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'started_on' => 'date',
            'completed_on' => 'date',
            'status' => ProductionOrderStatus::class,
            'quantity' => 'decimal:4',
            'produced_quantity' => 'decimal:4',
            'materials_cost' => 'decimal:4',
            'other_costs' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'unit_cost' => 'decimal:6',
        ];
    }
}
