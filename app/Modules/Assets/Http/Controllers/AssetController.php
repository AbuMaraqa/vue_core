<?php

namespace App\Modules\Assets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Controllers\Concerns\RendersReports;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Assets\Actions\CancelAsset;
use App\Modules\Assets\Actions\CreateAsset;
use App\Modules\Assets\Actions\DisposeAsset;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Enums\DepreciationMethod;
use App\Modules\Assets\Http\Requests\StoreAssetRequest;
use App\Modules\Assets\Http\Resources\AssetResource;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetCategory;
use App\Modules\Assets\Printing\AssetDocument;
use App\Modules\Assets\Reports\AssetRegister;
use App\Modules\Assets\Support\DepreciationSchedule;
use App\Modules\Foundation\Exports\Report;
use App\Modules\Foundation\Support\Filters\SearchFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The fixed asset register: assets, their depreciation schedule, disposal,
 * and the register report.
 */
class AssetController extends Controller
{
    use RendersReports;

    public function index(Request $request, Currencies $currencies): Response
    {
        Gate::authorize('viewAny', Asset::class);

        $assets = QueryBuilder::for(Asset::query()->with('category')->withExists('depreciations'))
            ->allowedFilters(
                SearchFilter::on('number', 'name', 'serial_number', 'location'),
                AllowedFilter::exact('category', 'category_id'),
                AllowedFilter::exact('status'),
            )
            ->defaultSort('-acquisition_date', '-id')
            ->allowedSorts('acquisition_date', 'number', 'cost')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/assets/assets/Index', [
            'assets' => AssetResource::collection($assets),
            'categories' => $this->categoryOptions(),
            'statuses' => AssetStatus::options(),
            'filters' => [
                'search' => $request->input('filter.search'),
                'category' => $request->input('filter.category'),
                'status' => $request->input('filter.status'),
            ],
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function create(AccountingOptions $options): Response
    {
        Gate::authorize('create', Asset::class);

        return Inertia::render('admin/assets/assets/Create', [
            'categories' => AssetCategory::query()->get()->map(fn (AssetCategory $category): array => [
                'value' => $category->id,
                'label' => $category->translate('name'),
                'method' => $category->method->value,
                'useful_life_months' => $category->useful_life_months,
            ])->sortBy('label')->values(),
            'methods' => DepreciationMethod::options(),
            'costCenters' => $options->costCenters(),
            'accounts' => $options->accounts(),
            'defaults' => ['acquisition_date' => now()->toDateString()],
        ]);
    }

    public function store(StoreAssetRequest $request, CreateAsset $createAsset): RedirectResponse
    {
        $asset = $createAsset->handle($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Asset :number registered.', ['number' => $asset->number])]);

        return to_route('admin.assets.assets.show', $asset);
    }

    public function show(Request $request, Asset $asset, DepreciationSchedule $schedule, AccountingOptions $options, Currencies $currencies): Response
    {
        Gate::authorize('view', $asset);

        $asset->load(['category', 'costCenter', 'fundingAccount', 'creator', 'depreciations.run', 'journalEntries']);
        $posted = $asset->depreciated_until?->format('Y-m');

        return Inertia::render('admin/assets/assets/Show', [
            'asset' => AssetResource::make($asset),
            'schedule' => array_map(fn (array $row): array => [
                ...$row,
                'posted' => $posted !== null && $row['month'] <= $posted,
            ], $schedule->rows($asset)),
            'costCenters' => $options->costCenters(),
            'proceedsAccounts' => $options->accounts(),
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    /**
     * The printable asset card.
     */
    public function pdf(Asset $asset): AssetDocument
    {
        Gate::authorize('view', $asset);

        return new AssetDocument($asset);
    }

    /**
     * Update what describes the asset; its figures stay as registered.
     */
    public function update(Request $request, Asset $asset): RedirectResponse
    {
        Gate::authorize('update', $asset);

        $asset->update($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:150'],
            'cost_center_id' => ['nullable', 'integer', Rule::exists(CostCenter::class, 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Asset updated.')]);

        return back();
    }

    public function dispose(Request $request, Asset $asset, DisposeAsset $disposeAsset): RedirectResponse
    {
        Gate::authorize('dispose', $asset);

        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:'.$asset->acquisition_date->toDateString()],
            'proceeds' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'proceeds_account_id' => ['nullable', 'integer', Rule::exists(Account::class, 'id')->where('is_group', false)->where('is_active', true)],
        ]);

        $disposeAsset->handle(
            $asset,
            $validated['date'],
            (string) ($validated['proceeds'] ?? '0'),
            isset($validated['proceeds_account_id']) ? (int) $validated['proceeds_account_id'] : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Asset disposed.')]);

        return back();
    }

    public function cancel(Asset $asset, CancelAsset $cancelAsset): RedirectResponse
    {
        Gate::authorize('cancel', $asset);

        $cancelAsset->handle($asset);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Asset cancelled.')]);

        return back();
    }

    /**
     * The register on a date; `?format=` downloads it.
     */
    public function register(Request $request, AssetRegister $report): SymfonyResponse
    {
        Gate::authorize('viewAny', Asset::class);

        $asOf = $request->date('as_of') ?? now();
        $category = $request->integer('category_id') ?: null;

        return $this->report(
            $request,
            'admin/assets/reports/Register',
            [
                'report' => $report->generate($asOf, $category),
                'filters' => ['as_of' => $asOf->toDateString(), 'category_id' => $category],
                'categories' => $this->categoryOptions(),
            ],
            fn (): Report => $report->export($asOf, $category),
            exportPermission: 'Asset Export',
        );
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    protected function categoryOptions(): array
    {
        return array_values(AssetCategory::query()->get()
            ->map(fn (AssetCategory $category): array => ['value' => $category->id, 'label' => $category->translate('name')])
            ->sortBy('label')
            ->all());
    }
}
