<?php

namespace App\Modules\Production\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Foundation\Support\Filters\DateFilter;
use App\Modules\Foundation\Support\Filters\SearchFilter;
use App\Modules\Inventory\Support\InventoryOptions;
use App\Modules\Production\Actions\ProductionOrders\CompleteProductionOrder;
use App\Modules\Production\Actions\ProductionOrders\DiscardProductionOrder;
use App\Modules\Production\Actions\ProductionOrders\ReverseProductionOrder;
use App\Modules\Production\Actions\ProductionOrders\SaveProductionOrder;
use App\Modules\Production\Actions\ProductionOrders\StartProductionOrder;
use App\Modules\Production\Enums\ProductionOrderStatus;
use App\Modules\Production\Http\Requests\ProductionOrders\CompleteProductionOrderRequest;
use App\Modules\Production\Http\Requests\ProductionOrders\SaveProductionOrderRequest;
use App\Modules\Production\Http\Resources\ProductionOrderResource;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Printing\ProductionOrderDocument;
use App\Modules\Production\Support\ProductionOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Production orders: plan a draft (from a recipe or by hand), start it to
 * issue the materials, complete it to bring the product into stock at its
 * real cost, or step it back.
 */
class ProductionOrderController extends Controller
{
    public function index(Request $request, InventoryOptions $options, Currencies $currencies): Response
    {
        Gate::authorize('viewAny', ProductionOrder::class);

        $orders = QueryBuilder::for(ProductionOrder::query()->with(['item.unit', 'contact']))
            ->allowedFilters(
                SearchFilter::on('number', 'reference', 'description'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('item', 'item_id'),
                AllowedFilter::exact('warehouse', 'product_warehouse_id'),
                DateFilter::from(),
                DateFilter::to(),
            )
            ->defaultSort('-date', '-id')
            ->allowedSorts('date', 'number')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/production/orders/Index', [
            'orders' => ProductionOrderResource::collection($orders),
            'statuses' => ProductionOrderStatus::options(),
            'warehouses' => $options->warehouses(),
            'counts' => ProductionOrder::query()->toBase()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'filters' => [
                'search' => $request->input('filter.search'),
                'status' => $request->input('filter.status'),
                'warehouse' => $request->input('filter.warehouse'),
                'from' => $request->input('filter.from'),
                'to' => $request->input('filter.to'),
            ],
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function create(Request $request, InventoryOptions $inventory, ProductionOptions $production, AccountingOptions $accounting, DefaultAccounts $defaults, Currencies $currencies): Response
    {
        Gate::authorize('create', ProductionOrder::class);

        $warehouse = $inventory->warehouses()[0]['value'] ?? null;

        return Inertia::render('admin/production/orders/Create', [
            ...$this->formOptions($inventory, $production, $accounting, $defaults, $currencies),
            'defaults' => [
                'date' => now()->toDateString(),
                'materials_warehouse_id' => $warehouse,
                'product_warehouse_id' => $warehouse,
                'bill_of_materials_id' => $request->integer('bom') ?: null,
            ],
        ]);
    }

    public function store(SaveProductionOrderRequest $request, SaveProductionOrder $saveOrder): RedirectResponse
    {
        $order = $saveOrder->handle(new ProductionOrder, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Production order :number created.', ['number' => $order->number])]);

        return to_route('admin.production.orders.show', $order);
    }

    public function show(ProductionOrder $order, Currencies $currencies): Response
    {
        Gate::authorize('view', $order);

        $order->load(['billOfMaterials', 'item.unit', 'materialsWarehouse', 'productWarehouse', 'contact', 'costCenter', 'creator', 'materials.item.unit', 'costs.account', 'journalEntries']);

        return Inertia::render('admin/production/orders/Show', [
            'order' => ProductionOrderResource::make($order),
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function pdf(ProductionOrder $order): ProductionOrderDocument
    {
        Gate::authorize('view', $order);

        return new ProductionOrderDocument($order);
    }

    public function edit(ProductionOrder $order, InventoryOptions $inventory, ProductionOptions $production, AccountingOptions $accounting, DefaultAccounts $defaults, Currencies $currencies): Response
    {
        Gate::authorize('update', $order);

        $order->load(['item.unit', 'contact', 'materials.item.unit', 'costs']);

        return Inertia::render('admin/production/orders/Edit', [
            'order' => ProductionOrderResource::make($order),
            ...$this->formOptions($inventory, $production, $accounting, $defaults, $currencies),
        ]);
    }

    public function update(SaveProductionOrderRequest $request, ProductionOrder $order, SaveProductionOrder $saveOrder): RedirectResponse
    {
        $saveOrder->handle($order, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Production order updated.')]);

        return to_route('admin.production.orders.show', $order);
    }

    public function destroy(ProductionOrder $order, DiscardProductionOrder $discardOrder): RedirectResponse
    {
        Gate::authorize('delete', $order);

        $deleted = $discardOrder->handle($order);

        Inertia::flash('toast', ['type' => 'success', 'message' => $deleted ? __('Production order deleted.') : __('Production order cancelled.')]);

        return $deleted ? to_route('admin.production.orders.index') : to_route('admin.production.orders.show', $order);
    }

    public function start(Request $request, ProductionOrder $order, StartProductionOrder $startOrder): RedirectResponse
    {
        Gate::authorize('start', $order);

        $validated = $request->validate(['date' => ['required', 'date']]);

        $startOrder->handle($order, $validated['date']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Production started. The materials were issued to work in progress.')]);

        return to_route('admin.production.orders.show', $order);
    }

    public function completion(ProductionOrder $order, AccountingOptions $accounting, DefaultAccounts $defaults, Currencies $currencies): Response
    {
        Gate::authorize('complete', $order);

        $order->load(['item.unit', 'materialsWarehouse', 'productWarehouse', 'materials.item.unit', 'costs']);

        return Inertia::render('admin/production/orders/Complete', [
            'order' => ProductionOrderResource::make($order),
            'accounts' => $accounting->accounts(),
            'defaultCostAccountId' => rescue(fn (): int => $defaults->id(AccountRole::AppliedProductionCosts), null, false),
            'today' => now()->toDateString(),
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function complete(CompleteProductionOrderRequest $request, ProductionOrder $order, CompleteProductionOrder $completeOrder): RedirectResponse
    {
        $completeOrder->handle($order, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Production completed. The product was added to stock at its cost.')]);

        return to_route('admin.production.orders.show', $order);
    }

    public function reverse(ProductionOrder $order, ReverseProductionOrder $reverseOrder): RedirectResponse
    {
        Gate::authorize('reverse', $order);

        $order = $reverseOrder->handle($order);

        Inertia::flash('toast', ['type' => 'success', 'message' => $order->status === ProductionOrderStatus::Draft
            ? __('Production order returned to draft. The materials are back in stock.')
            : __('Production order reopened. The product was taken out of stock.')]);

        return to_route('admin.production.orders.show', $order);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(InventoryOptions $inventory, ProductionOptions $production, AccountingOptions $accounting, DefaultAccounts $defaults, Currencies $currencies): array
    {
        return [
            'warehouses' => $inventory->warehouses(),
            'billsOfMaterials' => $production->billsOfMaterials(),
            'accounts' => $accounting->accounts(),
            'costCenters' => $accounting->costCenters(),
            'defaultCostAccountId' => rescue(fn (): int => $defaults->id(AccountRole::AppliedProductionCosts), null, false),
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ];
    }
}
