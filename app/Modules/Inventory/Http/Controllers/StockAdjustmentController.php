<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Foundation\Support\Filters\DateFilter;
use App\Modules\Foundation\Support\Filters\SearchFilter;
use App\Modules\Inventory\Actions\StockAdjustments\CancelStockAdjustment;
use App\Modules\Inventory\Actions\StockAdjustments\PostStockAdjustment;
use App\Modules\Inventory\Http\Requests\StockAdjustments\StoreStockAdjustmentRequest;
use App\Modules\Inventory\Http\Resources\StockAdjustmentResource;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Printing\StockAdjustmentDocument;
use App\Modules\Inventory\Support\InventoryOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Stock counts, damages, losses and opening stock. An adjustment is posted
 * when saved and can only be cancelled afterwards.
 */
class StockAdjustmentController extends Controller
{
    public function index(Request $request, InventoryOptions $options, Currencies $currencies): Response
    {
        Gate::authorize('viewAny', StockAdjustment::class);

        $adjustments = QueryBuilder::for(StockAdjustment::query()->with(['warehouse', 'lines']))
            ->allowedFilters(
                SearchFilter::on('number', 'description'),
                AllowedFilter::exact('warehouse', 'warehouse_id'),
                AllowedFilter::exact('status'),
                DateFilter::from(),
                DateFilter::to(),
            )
            ->defaultSort('-date', '-id')
            ->allowedSorts('date', 'number')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/inventory/adjustments/Index', [
            'adjustments' => StockAdjustmentResource::collection($adjustments),
            'warehouses' => $options->warehouses(),
            'statuses' => DocumentStatus::options(),
            'filters' => [
                'search' => $request->input('filter.search'),
                'warehouse' => $request->input('filter.warehouse'),
                'status' => $request->input('filter.status'),
                'from' => $request->input('filter.from'),
                'to' => $request->input('filter.to'),
            ],
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function create(InventoryOptions $options, AccountingOptions $accounting, DefaultAccounts $defaults): Response
    {
        Gate::authorize('create', StockAdjustment::class);

        return Inertia::render('admin/inventory/adjustments/Create', [
            'warehouses' => $options->warehouses(),
            'accounts' => $accounting->accounts(),
            'defaults' => [
                'date' => now()->toDateString(),
                'warehouse_id' => $options->warehouses()[0]['value'] ?? null,
                'account_id' => $defaults->id(AccountRole::InventoryAdjustment),
                'opening_account_id' => $defaults->id(AccountRole::OpeningBalanceEquity),
            ],
        ]);
    }

    public function store(StoreStockAdjustmentRequest $request, PostStockAdjustment $postAdjustment): RedirectResponse
    {
        $adjustment = $postAdjustment->handle($request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock adjustment :number posted.', ['number' => $adjustment->number])]);

        return to_route('admin.inventory.adjustments.show', $adjustment);
    }

    public function show(StockAdjustment $adjustment, Currencies $currencies): Response
    {
        Gate::authorize('view', $adjustment);

        return Inertia::render('admin/inventory/adjustments/Show', [
            'adjustment' => StockAdjustmentResource::make($adjustment->load(['warehouse', 'account', 'creator', 'lines.item', 'journalEntries'])),
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function pdf(StockAdjustment $adjustment): StockAdjustmentDocument
    {
        Gate::authorize('view', $adjustment);

        return new StockAdjustmentDocument($adjustment);
    }

    public function cancel(StockAdjustment $adjustment, CancelStockAdjustment $cancelAdjustment): RedirectResponse
    {
        Gate::authorize('cancel', $adjustment);

        $cancelAdjustment->handle($adjustment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock adjustment cancelled.')]);

        return to_route('admin.inventory.adjustments.show', $adjustment);
    }
}
