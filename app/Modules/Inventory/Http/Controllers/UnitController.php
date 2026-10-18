<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Support\SafeDelete;
use App\Modules\Foundation\Http\Requests\Concerns\ValidatesTranslations;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Units of measure.
 */
class UnitController extends Controller
{
    use ValidatesTranslations;

    public function index(): Response
    {
        Gate::authorize('viewAny', Unit::class);

        return Inertia::render('admin/inventory/units/Index', [
            'units' => Unit::query()->get()
                ->map(fn (Unit $unit): array => [
                    'id' => $unit->id,
                    'name' => $unit->translate('name'),
                    'name_translations' => $unit->name,
                    'symbol' => $unit->symbol,
                    'is_active' => $unit->is_active,
                ])
                ->sortBy('name')
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Unit::class);

        Unit::query()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unit created.')]);

        return to_route('admin.inventory.units.index');
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        Gate::authorize('update', $unit);

        $unit->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unit updated.')]);

        return to_route('admin.inventory.units.index');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        Gate::authorize('delete', $unit);

        SafeDelete::delete($unit);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unit deleted.')]);

        return to_route('admin.inventory.units.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            ...$this->translationRules('name', 50),
            'symbol' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);

        return [...$validated, 'name' => array_filter($validated['name']), 'is_active' => $request->boolean('is_active', true)];
    }
}
