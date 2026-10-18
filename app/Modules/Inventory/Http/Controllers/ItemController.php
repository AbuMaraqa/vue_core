<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Enums\TaxScope;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\SafeDelete;
use App\Modules\Foundation\Http\Requests\ExportRequest;
use App\Modules\Foundation\Http\Requests\ImportRequest;
use App\Modules\Foundation\Support\Filters\SearchFilter;
use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Exports\ItemsExporter;
use App\Modules\Inventory\Http\Requests\Items\SaveItemRequest;
use App\Modules\Inventory\Http\Resources\ItemResource;
use App\Modules\Inventory\Imports\ItemsImporter;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Printing\ItemCardDocument;
use App\Modules\Inventory\Support\InventoryOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Items and services, with their stock in every warehouse.
 */
class ItemController extends Controller
{
    public function index(Request $request, InventoryOptions $options, Currencies $currencies): Response
    {
        Gate::authorize('viewAny', Item::class);

        $items = $this->query()
            ->with(['category', 'unit'])
            ->withExists('movements')
            ->withSum('stockLevels', 'quantity')
            ->withSum('stockLevels', 'value')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/inventory/items/Index', [
            'items' => ItemResource::collection($items),
            'categories' => $options->categories(),
            'types' => ItemType::options(),
            'filters' => [
                'search' => $request->input('filter.search'),
                'category' => $request->input('filter.category'),
                'type' => $request->input('filter.type'),
                'status' => $request->input('filter.status'),
                'low_stock' => $request->boolean('filter.low_stock'),
                'sort' => $request->input('sort', 'code'),
            ],
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function create(Request $request, InventoryOptions $options, AccountingOptions $accounting): Response
    {
        Gate::authorize('create', Item::class);

        return Inertia::render('admin/inventory/items/Create', [
            ...$this->formOptions($options, $accounting),
            'type' => $request->query('type') === ItemType::Service->value ? ItemType::Service->value : ItemType::Stock->value,
        ]);
    }

    public function store(SaveItemRequest $request): RedirectResponse
    {
        $item = $this->createItem($request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item created.')]);

        return to_route('admin.inventory.items.show', $item);
    }

    /**
     * Create an item from the item picker of a document form, with only its
     * essential fields, and return it as a picker option.
     */
    public function quickStore(SaveItemRequest $request, InventoryOptions $options): JsonResponse
    {
        $item = $this->createItem($request)->load('unit');

        return response()->json($options->itemOption($item), SymfonyResponse::HTTP_CREATED);
    }

    public function show(Item $item, Currencies $currencies): Response
    {
        Gate::authorize('view', $item);

        $item->load(['category', 'unit', 'salesTax', 'purchaseTax', 'salesAccount', 'purchaseAccount', 'inventoryAccount', 'costOfSalesAccount']);

        return Inertia::render('admin/inventory/items/Show', [
            'item' => ItemResource::make($item),
            'accounts' => [
                'sales' => $item->salesAccount?->label(),
                'purchase' => $item->purchaseAccount?->label(),
                'inventory' => $item->inventoryAccount?->label(),
                'cost_of_sales' => $item->costOfSalesAccount?->label(),
            ],
            'taxes' => [
                'sales' => $item->salesTax?->translate('name'),
                'purchase' => $item->purchaseTax?->translate('name'),
            ],
            'levels' => $item->stockLevels()->with('warehouse')->get()
                ->map(fn (StockLevel $level): array => [
                    'warehouse_id' => $level->warehouse_id,
                    'warehouse' => $level->warehouse->label(),
                    'quantity' => (string) $level->quantity,
                    'average_cost' => (string) $level->average_cost,
                    'value' => (string) $level->value,
                ])
                ->values(),
            'recentMovements' => $item->movements()->with(['warehouse', 'source'])->latest('date')->latest('id')->limit(10)->get()
                ->map(fn (StockMovement $movement): array => [
                    'id' => $movement->id,
                    'date' => $movement->date->toDateString(),
                    'type' => $movement->type->label(),
                    'document' => $movement->source?->getAttribute('number'),
                    'warehouse' => $movement->warehouse->label(),
                    'quantity' => (string) $movement->quantity,
                    'value' => (string) $movement->value,
                ])
                ->values(),
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function pdf(Item $item): ItemCardDocument
    {
        Gate::authorize('view', $item);

        return new ItemCardDocument($item);
    }

    public function edit(Item $item, InventoryOptions $options, AccountingOptions $accounting): Response
    {
        Gate::authorize('update', $item);

        return Inertia::render('admin/inventory/items/Edit', [
            ...$this->formOptions($options, $accounting),
            'item' => ItemResource::make($item),
        ]);
    }

    public function update(SaveItemRequest $request, Item $item): RedirectResponse
    {
        $item->update($request->attributesToSave());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item updated.')]);

        return to_route('admin.inventory.items.show', $item);
    }

    public function destroy(Item $item): RedirectResponse
    {
        Gate::authorize('delete', $item);

        SafeDelete::delete($item);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item deleted.')]);

        return to_route('admin.inventory.items.index');
    }

    /**
     * Items for the item picker of document forms. Any staff member who can
     * fill in a document may search them; costs are not included.
     */
    public function search(Request $request, InventoryOptions $options): JsonResponse
    {
        return response()->json($options->searchItems(
            $request->string('search')->trim()->value(),
            $request->enum('type', ItemType::class),
        ));
    }

    /**
     * The permission is checked by the route, before the format is validated.
     */
    public function export(ExportRequest $request): SymfonyResponse
    {
        return ItemsExporter::from($this->query()->with(['category', 'unit'])->withSum('stockLevels', 'quantity'))
            ->download($request->exportFormat());
    }

    public function import(ImportRequest $request): RedirectResponse
    {
        $result = (new ItemsImporter)->import($request->spreadsheet());

        Inertia::flash([
            'toast' => [
                'type' => $result->hasFailures() ? 'warning' : 'success',
                'message' => trans_choice('{0} No items were imported.|{1} One item was imported.|[2,*] :count items were imported.', $result->imported),
            ],
            'importResult' => $result->toArray(),
        ]);

        return back();
    }

    public function template(): SymfonyResponse
    {
        return (new ItemsImporter)->template('items-import-template.xlsx');
    }

    /**
     * Create the item; one saved without a code gets `I-00042` from its id.
     */
    protected function createItem(SaveItemRequest $request): Item
    {
        return DB::transaction(function () use ($request): Item {
            $item = Item::query()->create($request->attributesToSave());
            $item->assignGeneratedCode();

            return $item;
        });
    }

    /**
     * The filtered and sorted list, shared by the page and its exports.
     *
     * @return QueryBuilder<Item>
     */
    protected function query(): QueryBuilder
    {
        return QueryBuilder::for(Item::class)
            ->allowedFilters(
                SearchFilter::on('code', 'barcode', ...Item::translationColumns('name')),
                AllowedFilter::exact('category', 'category_id'),
                AllowedFilter::exact('type'),
                AllowedFilter::callback('status', fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))->delimiter(''),
                AllowedFilter::callback('low_stock', fn (Builder $query, mixed $enabled) => $query
                    ->when(filter_var($enabled, FILTER_VALIDATE_BOOLEAN), fn (Builder $low) => $low->lowStock()))
                    ->delimiter(''),
            )
            ->allowedSorts('code', 'created_at', 'sale_price')
            ->defaultSort('code');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(InventoryOptions $options, AccountingOptions $accounting): array
    {
        return [
            'types' => ItemType::options(),
            'categories' => $options->categories(),
            'units' => $options->units(),
            'salesTaxes' => $accounting->taxes(TaxScope::Sales),
            'purchaseTaxes' => $accounting->taxes(TaxScope::Purchases),
            'revenueAccounts' => $accounting->accounts(AccountSubtype::OperatingRevenue, AccountSubtype::OtherRevenue),
            'expenseAccounts' => $accounting->accounts(AccountSubtype::CostOfSales, AccountSubtype::OperatingExpense, AccountSubtype::OtherExpense),
            'inventoryAccounts' => $accounting->accounts(AccountSubtype::Inventory),
        ];
    }
}
