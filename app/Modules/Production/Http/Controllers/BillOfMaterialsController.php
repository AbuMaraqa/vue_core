<?php

namespace App\Modules\Production\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Accounting\Support\SafeDelete;
use App\Modules\Foundation\Support\Filters\SearchFilter;
use App\Modules\Production\Actions\BillsOfMaterials\SaveBillOfMaterials;
use App\Modules\Production\Http\Requests\BillsOfMaterials\SaveBillOfMaterialsRequest;
use App\Modules\Production\Http\Resources\BillOfMaterialsResource;
use App\Modules\Production\Models\BillOfMaterials;
use App\Modules\Production\Support\CostEstimator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Recipes of the products: which materials make them and what else a batch
 * costs, with an estimate of the cost at today's prices.
 */
class BillOfMaterialsController extends Controller
{
    public function index(Request $request, CostEstimator $estimator, Currencies $currencies): Response
    {
        Gate::authorize('viewAny', BillOfMaterials::class);

        $boms = QueryBuilder::for(BillOfMaterials::query()->with(['item.unit', 'components', 'costs'])->withCount('components'))
            ->allowedFilters(
                SearchFilter::on('code', 'name'),
                AllowedFilter::exact('item', 'item_id'),
                AllowedFilter::callback('status', fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))->delimiter(''),
            )
            ->defaultSort('code')
            ->allowedSorts('code', 'name', 'created_at')
            ->paginate(20)
            ->withQueryString();

        $estimates = $boms->getCollection()->mapWithKeys(fn (BillOfMaterials $bom): array => [$bom->id => $estimator->estimate($bom)]);

        return Inertia::render('admin/production/boms/Index', [
            'boms' => BillOfMaterialsResource::collection($boms),
            'estimates' => $estimates,
            'filters' => [
                'search' => $request->input('filter.search'),
                'status' => $request->input('filter.status'),
            ],
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function create(AccountingOptions $accounting, DefaultAccounts $defaults, Currencies $currencies): Response
    {
        Gate::authorize('create', BillOfMaterials::class);

        return Inertia::render('admin/production/boms/Create', [
            ...$this->formOptions($accounting, $defaults, $currencies),
        ]);
    }

    public function store(SaveBillOfMaterialsRequest $request, SaveBillOfMaterials $saveBom): RedirectResponse
    {
        $saveBom->handle(new BillOfMaterials, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bill of materials created.')]);

        return to_route('admin.production.boms.index');
    }

    public function edit(BillOfMaterials $bom, AccountingOptions $accounting, DefaultAccounts $defaults, Currencies $currencies, CostEstimator $estimator): Response
    {
        Gate::authorize('update', $bom);

        $bom->load(['item.unit', 'components.item.unit', 'costs']);

        return Inertia::render('admin/production/boms/Edit', [
            'bom' => BillOfMaterialsResource::make($bom),
            'estimate' => $estimator->estimate($bom),
            ...$this->formOptions($accounting, $defaults, $currencies),
        ]);
    }

    public function update(SaveBillOfMaterialsRequest $request, BillOfMaterials $bom, SaveBillOfMaterials $saveBom): RedirectResponse
    {
        $saveBom->handle($bom, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bill of materials updated.')]);

        return to_route('admin.production.boms.index');
    }

    public function destroy(BillOfMaterials $bom): RedirectResponse
    {
        Gate::authorize('delete', $bom);

        SafeDelete::delete($bom);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bill of materials deleted.')]);

        return to_route('admin.production.boms.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(AccountingOptions $accounting, DefaultAccounts $defaults, Currencies $currencies): array
    {
        return [
            'accounts' => $accounting->accounts(),
            'defaultCostAccountId' => rescue(fn (): int => $defaults->id(AccountRole::AppliedProductionCosts), null, false),
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ];
    }
}
