<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Support\SafeDelete;
use App\Modules\Foundation\Http\Requests\Concerns\ValidatesTranslations;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cost centers: departments, branches or projects that revenue and expenses
 * are tracked by. They form a tree, like the chart of accounts.
 */
class CostCenterController extends Controller
{
    use ValidatesTranslations;

    public function index(): Response
    {
        Gate::authorize('viewAny', CostCenter::class);

        $centers = CostCenter::query()->orderBy('code')->get();
        $children = $centers->groupBy(fn (CostCenter $center): int => $center->parent_id ?? 0);
        $rows = [];

        $walk = function (int $parentId, int $depth) use (&$walk, &$rows, $children): void {
            foreach ($children->get($parentId, []) as $center) {
                $rows[] = [
                    'id' => $center->id,
                    'parent_id' => $center->parent_id,
                    'code' => $center->code,
                    'name' => $center->translate('name'),
                    'name_translations' => $center->name,
                    'is_active' => $center->is_active,
                    'depth' => $depth,
                    'has_children' => $children->has($center->id),
                ];

                $walk($center->id, $depth + 1);
            }
        };

        $walk(0, 0);

        return Inertia::render('admin/accounting/cost-centers/Index', [
            'centers' => $rows,
            'parents' => $centers->map(fn (CostCenter $center): array => ['value' => $center->id, 'label' => $center->label()])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', CostCenter::class);

        CostCenter::query()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cost center created.')]);

        return to_route('admin.accounting.cost-centers.index');
    }

    public function update(Request $request, CostCenter $costCenter): RedirectResponse
    {
        Gate::authorize('update', $costCenter);

        $costCenter->update($this->validated($request, $costCenter));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cost center updated.')]);

        return to_route('admin.accounting.cost-centers.index');
    }

    public function destroy(CostCenter $costCenter): RedirectResponse
    {
        Gate::authorize('delete', $costCenter);

        SafeDelete::delete($costCenter);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cost center deleted.')]);

        return to_route('admin.accounting.cost-centers.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?CostCenter $center = null): array
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', Rule::exists(CostCenter::class, 'id'), function (string $attribute, mixed $value, Closure $fail) use ($center): void {
                $current = $value ? CostCenter::query()->find((int) $value) : null;

                while ($center && $current) {
                    if ($current->id === $center->id) {
                        $fail(__('A cost center cannot be placed under itself or one of its children.'));

                        return;
                    }

                    $current = $current->parent;
                }
            }],
            'code' => ['required', 'string', 'max:20', Rule::unique(CostCenter::class, 'code')->ignore($center)],
            ...$this->translationRules('name'),
            'is_active' => ['boolean'],
        ]);

        return [
            ...$validated,
            'name' => array_filter($validated['name']),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
