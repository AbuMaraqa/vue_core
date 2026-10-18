<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Foundation\Concerns\HasTranslations;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A unit of measure, e.g. piece, box or kilogram.
 *
 * @property int $id
 * @property array<string, string> $name
 * @property string|null $symbol
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'symbol', 'is_active'])]
#[UseFactory(UnitFactory::class)]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory, HasTranslations;

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
