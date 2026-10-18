<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Enums\TaxScope;
use App\Modules\Foundation\Concerns\HasTranslations;
use Database\Factories\TaxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A tax rate, e.g. "VAT 16%". Tax charged on sales is owed to the tax
 * authority (sales account); tax paid on purchases is recoverable (purchase
 * account).
 *
 * @property int $id
 * @property array<string, string> $name
 * @property string $rate Percentage, e.g. `16.0000`.
 * @property TaxScope $scope
 * @property int|null $sales_account_id
 * @property int|null $purchase_account_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account|null $salesAccount
 * @property-read Account|null $purchaseAccount
 */
#[Fillable(['name', 'rate', 'scope', 'sales_account_id', 'purchase_account_id', 'is_active'])]
#[UseFactory(TaxFactory::class)]
class Tax extends Model
{
    /** @use HasFactory<TaxFactory> */
    use HasFactory, HasTranslations;

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
            'rate' => 'decimal:4',
            'scope' => TaxScope::class,
            'is_active' => 'boolean',
        ];
    }
}
