<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Foundation\Concerns\HasTranslations;
use App\Modules\Inventory\Enums\ItemType;
use Database\Factories\ItemFactory;
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
 * Something the company sells or buys: a stock item, counted in warehouses
 * at a weighted average cost, or a service.
 *
 * Each account is taken from the item, else its category, else the default
 * account of the accounting settings.
 *
 * @property int $id
 * @property string $code
 * @property string|null $barcode
 * @property array<string, string> $name
 * @property string|null $description
 * @property ItemType $type
 * @property int|null $category_id
 * @property int|null $unit_id
 * @property string $sale_price
 * @property string $purchase_price
 * @property int|null $sales_tax_id
 * @property int|null $purchase_tax_id
 * @property int|null $sales_account_id
 * @property int|null $purchase_account_id
 * @property int|null $inventory_account_id
 * @property int|null $cost_of_sales_account_id
 * @property string|null $reorder_level
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ItemCategory|null $category
 * @property-read Unit|null $unit
 * @property-read Tax|null $salesTax
 * @property-read Tax|null $purchaseTax
 * @property-read bool|null $movements_exists
 */
#[Fillable([
    'code', 'barcode', 'name', 'description', 'type', 'category_id', 'unit_id', 'sale_price', 'purchase_price',
    'sales_tax_id', 'purchase_tax_id', 'sales_account_id', 'purchase_account_id', 'inventory_account_id',
    'cost_of_sales_account_id', 'reorder_level', 'is_active',
])]
#[UseFactory(ItemFactory::class)]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, HasTranslations, LogsActivity;

    /**
     * Marks the code of an item saved without one, until it gets its real code.
     */
    public const string TEMPORARY_CODE_PREFIX = 'new-';

    /**
     * @return BelongsTo<ItemCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<Tax, $this>
     */
    public function salesTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'sales_tax_id');
    }

    /**
     * @return BelongsTo<Tax, $this>
     */
    public function purchaseTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'purchase_tax_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function salesAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'sales_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function purchaseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'purchase_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'inventory_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function costOfSalesAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cost_of_sales_account_id');
    }

    /**
     * @return HasMany<StockLevel, $this>
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isStock(): bool
    {
        return $this->type === ItemType::Stock;
    }

    /**
     * Where its sales are credited.
     */
    public function salesAccountId(): int
    {
        return $this->resolveAccount('sales_account_id', AccountRole::Sales);
    }

    /**
     * Where its purchases are debited: the inventory account for stock items,
     * else the purchases (expense) account.
     */
    public function purchaseAccountId(): int
    {
        return $this->isStock()
            ? $this->inventoryAccountId()
            : $this->resolveAccount('purchase_account_id', AccountRole::Purchases);
    }

    /**
     * Where the value of its stock is kept.
     */
    public function inventoryAccountId(): int
    {
        return $this->resolveAccount('inventory_account_id', AccountRole::Inventory);
    }

    /**
     * Where the cost of what is sold is charged.
     */
    public function costOfSalesAccountId(): int
    {
        return $this->resolveAccount('cost_of_sales_account_id', AccountRole::CostOfSales);
    }

    /**
     * Items with a reorder level whose quantity on hand fell to it or below.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLowStock(Builder $query): void
    {
        $query->whereNotNull('reorder_level')
            ->whereRaw('COALESCE((SELECT SUM(quantity) FROM stock_levels WHERE stock_levels.item_id = items.id), 0) <= items.reorder_level');
    }

    public function label(): string
    {
        return "{$this->code} - {$this->translate('name')}";
    }

    /**
     * Replace a temporary code with `I-00042` from the id, adding a suffix if
     * someone already typed that code by hand.
     */
    public function assignGeneratedCode(): void
    {
        if (! str_starts_with($this->code, self::TEMPORARY_CODE_PREFIX)) {
            return;
        }

        $base = sprintf('I-%05d', $this->id);
        $code = $base;
        $suffix = 1;

        while (self::query()->where('code', $code)->whereKeyNot($this->id)->exists()) {
            $code = "{$base}-".$suffix++;
        }

        $this->update(['code' => $code]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('items')
            ->logOnly(['code', 'name', 'type', 'sale_price', 'purchase_price', 'category_id', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function resolveAccount(string $column, AccountRole $role): int
    {
        $own = $this->getAttribute($column);

        if (is_int($own)) {
            return $own;
        }

        $fromCategory = $this->category?->getAttribute($column);

        return is_int($fromCategory) ? $fromCategory : app(DefaultAccounts::class)->id($role);
    }

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'type' => ItemType::class,
            'sale_price' => 'decimal:4',
            'purchase_price' => 'decimal:4',
            'reorder_level' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }
}
