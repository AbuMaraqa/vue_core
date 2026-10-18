<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Accounting\Models\Account;
use App\Modules\Foundation\Concerns\HasTranslations;
use Database\Factories\ItemCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A group of items. Its accounts, when set, replace the defaults for every
 * item of the category that has no account of its own.
 *
 * @property int $id
 * @property array<string, string> $name
 * @property int|null $inventory_account_id
 * @property int|null $cost_of_sales_account_id
 * @property int|null $sales_account_id
 * @property int|null $purchase_account_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account|null $inventoryAccount
 * @property-read Account|null $costOfSalesAccount
 * @property-read Account|null $salesAccount
 * @property-read Account|null $purchaseAccount
 */
#[Fillable(['name', 'inventory_account_id', 'cost_of_sales_account_id', 'sales_account_id', 'purchase_account_id'])]
#[UseFactory(ItemCategoryFactory::class)]
class ItemCategory extends Model
{
    /** @use HasFactory<ItemCategoryFactory> */
    use HasFactory, HasTranslations;

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'category_id');
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

    protected function casts(): array
    {
        return [
            'name' => 'array',
        ];
    }
}
