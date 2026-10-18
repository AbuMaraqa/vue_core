<?php

namespace App\Modules\Inventory\Imports;

use App\Modules\Foundation\Imports\Importer;
use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Validation\Rule;

/**
 * Create items in bulk. `type` is `stock` (default) or `service`; a
 * `category` or `unit` that does not exist yet is created with the given
 * name. Opening quantities are entered afterwards as a stock adjustment.
 */
class ItemsImporter extends Importer
{
    /**
     * @var array<string, int>
     */
    protected array $categories = [];

    /**
     * @var array<string, int>
     */
    protected array $units = [];

    public function columns(): array
    {
        return ['code', 'name_ar', 'name_en', 'type', 'category', 'unit', 'barcode', 'sale_price', 'purchase_price', 'reorder_level'];
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[0-9A-Za-z.\-_\/]+$/', 'distinct', Rule::unique(Item::class, 'code')],
            'name_ar' => ['required_without:name_en', 'nullable', 'string', 'max:150'],
            'name_en' => ['required_without:name_ar', 'nullable', 'string', 'max:150'],
            'type' => ['nullable', Rule::enum(ItemType::class)],
            'category' => ['nullable', 'string', 'max:150'],
            'unit' => ['nullable', 'string', 'max:50'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
        ];
    }

    protected function importRow(array $row): void
    {
        Item::query()->create([
            'code' => $row['code'],
            'name' => array_filter(['ar' => $row['name_ar'] ?? null, 'en' => $row['name_en'] ?? null]),
            'type' => $row['type'] ?? ItemType::Stock->value,
            'category_id' => filled($row['category'] ?? null) ? $this->categoryId((string) $row['category']) : null,
            'unit_id' => filled($row['unit'] ?? null) ? $this->unitId((string) $row['unit']) : null,
            'barcode' => $row['barcode'] ?? null,
            'sale_price' => $row['sale_price'] ?? 0,
            'purchase_price' => $row['purchase_price'] ?? 0,
            'reorder_level' => $row['reorder_level'] ?? null,
        ]);
    }

    protected function categoryId(string $name): int
    {
        return $this->categories[$name] ??= $this->findByName(ItemCategory::class, $name)
            ?? ItemCategory::query()->create(['name' => ['ar' => $name, 'en' => $name]])->id;
    }

    protected function unitId(string $name): int
    {
        return $this->units[$name] ??= $this->findByName(Unit::class, $name)
            ?? Unit::query()->create(['name' => ['ar' => $name, 'en' => $name]])->id;
    }

    /**
     * @param  class-string<ItemCategory|Unit>  $model
     */
    protected function findByName(string $model, string $name): ?int
    {
        $query = $model::query();

        foreach ($model::translationColumns('name') as $column) {
            $query->orWhere($column, $name);
        }

        $id = $query->value('id');

        return is_numeric($id) ? (int) $id : null;
    }
}
