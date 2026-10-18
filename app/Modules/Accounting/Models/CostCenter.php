<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Foundation\Concerns\HasTranslations;
use Database\Factories\CostCenterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A department, branch or project that revenue and expenses are tracked by.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $code
 * @property array<string, string> $name
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CostCenter|null $parent
 */
#[Fillable(['parent_id', 'code', 'name', 'is_active'])]
#[UseFactory(CostCenterFactory::class)]
class CostCenter extends Model
{
    /** @use HasFactory<CostCenterFactory> */
    use HasFactory, HasTranslations;

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<CostCenter, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function label(): string
    {
        return "{$this->code} - {$this->translate('name')}";
    }

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
