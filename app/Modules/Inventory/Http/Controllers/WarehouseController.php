<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\SafeDelete;
use App\Modules\Foundation\Http\Requests\Concerns\ValidatesTranslations;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Warehouses, with the value of the stock each one holds.
 */
class WarehouseController extends Controller
{
    use ValidatesTranslations;

    public function index(Request $request, Currencies $currencies): Response
    {
        Gate::authorize('viewAny', Warehouse::class);

        return Inertia::render('admin/inventory/warehouses/Index', [
            'warehouses' => Warehouse::query()->withExists('movements')->withSum('stockLevels', 'value')->orderBy('code')->get()
                ->map(fn (Warehouse $warehouse): array => [
                    'id' => $warehouse->id,
                    'code' => $warehouse->code,
                    'name' => $warehouse->translate('name'),
                    'name_translations' => $warehouse->name,
                    'address' => $warehouse->address,
                    'is_active' => $warehouse->is_active,
                    'stock_value' => (string) ($warehouse->getAttribute('stock_levels_sum_value') ?? '0'),
                    'can' => [
                        'update' => $request->user()?->can('update', $warehouse) ?? false,
                        'delete' => $request->user()?->can('delete', $warehouse) ?? false,
                    ],
                ]),
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Warehouse::class);

        Warehouse::query()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Warehouse created.')]);

        return to_route('admin.inventory.warehouses.index');
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        Gate::authorize('update', $warehouse);

        $warehouse->update($this->validated($request, $warehouse));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Warehouse updated.')]);

        return to_route('admin.inventory.warehouses.index');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        Gate::authorize('delete', $warehouse);

        SafeDelete::delete($warehouse);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Warehouse deleted.')]);

        return to_route('admin.inventory.warehouses.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[0-9A-Za-z.\-_]+$/', Rule::unique(Warehouse::class, 'code')->ignore($warehouse)],
            ...$this->translationRules('name', 100),
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        return [...$validated, 'name' => array_filter($validated['name']), 'is_active' => $request->boolean('is_active', true)];
    }
}
