<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Foundation\Support\Filters\DateFilter;
use App\Modules\Foundation\Support\Filters\SearchFilter;
use App\Modules\Inventory\Actions\StockTransfers\CancelStockTransfer;
use App\Modules\Inventory\Actions\StockTransfers\PostStockTransfer;
use App\Modules\Inventory\Http\Requests\StockTransfers\StoreStockTransferRequest;
use App\Modules\Inventory\Http\Resources\StockTransferResource;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Printing\StockTransferDocument;
use App\Modules\Inventory\Support\InventoryOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Stock moved between warehouses. A transfer is posted when saved and can
 * only be cancelled afterwards.
 */
class StockTransferController extends Controller
{
    public function index(Request $request, InventoryOptions $options): Response
    {
        Gate::authorize('viewAny', StockTransfer::class);

        $transfers = QueryBuilder::for(StockTransfer::query()->with(['fromWarehouse', 'toWarehouse'])->withCount('lines'))
            ->allowedFilters(
                SearchFilter::on('number', 'description'),
                AllowedFilter::callback('warehouse', fn (Builder $query, string $warehouse) => $query->where(fn (Builder $either) => $either
                    ->where('from_warehouse_id', $warehouse)
                    ->orWhere('to_warehouse_id', $warehouse)))->delimiter(''),
                AllowedFilter::exact('status'),
                DateFilter::from(),
                DateFilter::to(),
            )
            ->defaultSort('-date', '-id')
            ->allowedSorts('date', 'number')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/inventory/transfers/Index', [
            'transfers' => StockTransferResource::collection($transfers),
            'warehouses' => $options->warehouses(),
            'statuses' => DocumentStatus::options(),
            'filters' => [
                'search' => $request->input('filter.search'),
                'warehouse' => $request->input('filter.warehouse'),
                'status' => $request->input('filter.status'),
                'from' => $request->input('filter.from'),
                'to' => $request->input('filter.to'),
            ],
        ]);
    }

    public function create(InventoryOptions $options): Response
    {
        Gate::authorize('create', StockTransfer::class);

        $warehouses = $options->warehouses();

        return Inertia::render('admin/inventory/transfers/Create', [
            'warehouses' => $warehouses,
            'defaults' => [
                'date' => now()->toDateString(),
                'from_warehouse_id' => $warehouses[0]['value'] ?? null,
                'to_warehouse_id' => $warehouses[1]['value'] ?? null,
            ],
        ]);
    }

    public function store(StoreStockTransferRequest $request, PostStockTransfer $postTransfer): RedirectResponse
    {
        $transfer = $postTransfer->handle($request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock transfer :number posted.', ['number' => $transfer->number])]);

        return to_route('admin.inventory.transfers.show', $transfer);
    }

    public function show(StockTransfer $transfer): Response
    {
        Gate::authorize('view', $transfer);

        return Inertia::render('admin/inventory/transfers/Show', [
            'transfer' => StockTransferResource::make($transfer->load(['fromWarehouse', 'toWarehouse', 'creator', 'lines.item.unit'])),
        ]);
    }

    public function pdf(StockTransfer $transfer): StockTransferDocument
    {
        Gate::authorize('view', $transfer);

        return new StockTransferDocument($transfer);
    }

    public function cancel(StockTransfer $transfer, CancelStockTransfer $cancelTransfer): RedirectResponse
    {
        Gate::authorize('cancel', $transfer);

        $cancelTransfer->handle($transfer);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock transfer cancelled.')]);

        return to_route('admin.inventory.transfers.show', $transfer);
    }
}
