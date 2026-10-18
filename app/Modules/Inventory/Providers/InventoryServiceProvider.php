<?php

namespace App\Modules\Inventory\Providers;

use App\Models\User;
use App\Modules\Accounting\Events\BaseCurrencyChanged;
use App\Modules\Accounting\Support\DocumentLinks;
use App\Modules\Core\Support\Dashboard\Dashboard;
use App\Modules\Core\Support\Dashboard\DashboardShortcut;
use App\Modules\Core\Support\Dashboard\DashboardStat;
use App\Modules\Core\Support\ModuleServiceProvider;
use App\Modules\Core\Support\Sidebar\Sidebar;
use App\Modules\Core\Support\Sidebar\SidebarGroup;
use App\Modules\Core\Support\Sidebar\SidebarItem;
use App\Modules\Inventory\Listeners\ConvertStockToNewBaseCurrency;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Policies\ItemCategoryPolicy;
use App\Modules\Inventory\Policies\ItemPolicy;
use App\Modules\Inventory\Policies\StockAdjustmentPolicy;
use App\Modules\Inventory\Policies\StockTransferPolicy;
use App\Modules\Inventory\Policies\UnitPolicy;
use App\Modules\Inventory\Policies\WarehousePolicy;
use Illuminate\Support\Facades\Event;

/**
 * The Inventory module: items, warehouses and the stock ledger at weighted
 * average cost. Stock documents post their value through the Accounting
 * module; sales and purchases move stock through StockLedger.
 */
class InventoryServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Item::class => ItemPolicy::class,
        ItemCategory::class => ItemCategoryPolicy::class,
        Unit::class => UnitPolicy::class,
        Warehouse::class => WarehousePolicy::class,
        StockAdjustment::class => StockAdjustmentPolicy::class,
        StockTransfer::class => StockTransferPolicy::class,
    ];

    protected array $routes = [
        'routes/admin.php',
    ];

    public function boot(): void
    {
        parent::boot();

        $this->loadViewsFrom($this->modulePath('resources/views'), 'inventory');

        Event::listen(BaseCurrencyChanged::class, ConvertStockToNewBaseCurrency::class);

        $links = $this->app->make(DocumentLinks::class);
        $links->register(StockAdjustment::class, fn (StockAdjustment $adjustment): array => [
            'label' => __('Stock adjustment'),
            'url' => route('admin.inventory.adjustments.show', $adjustment),
        ]);
        $links->register(StockTransfer::class, fn (StockTransfer $transfer): array => [
            'label' => __('Stock transfer'),
            'url' => route('admin.inventory.transfers.show', $transfer),
        ]);
    }

    protected function modulePath(string $path = ''): string
    {
        return dirname(__DIR__).($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }

    protected function sidebar(Sidebar $sidebar): void
    {
        $sidebar->section('accounting', 'Accounting', sort: 30)->add(
            SidebarGroup::make('Inventory')
                ->simpleTitle('Goods and stock')
                ->icon('box')
                ->sort(40)
                ->add(
                    SidebarItem::make('Items')
                        ->route('admin.inventory.items.index')
                        ->activeWhen('admin.inventory.items.*')
                        ->can('Item View List'),
                    SidebarItem::make('Stock Adjustments')
                        ->simpleTitle('Stock count and damaged goods')
                        ->route('admin.inventory.adjustments.index')
                        ->activeWhen('admin.inventory.adjustments.*')
                        ->can('Stock Adjustment View List'),
                    SidebarItem::make('Stock Transfers')
                        ->route('admin.inventory.transfers.index')
                        ->activeWhen('admin.inventory.transfers.*')
                        ->can('Stock Transfer View List')
                        ->advanced(),
                    SidebarItem::make('Stock Valuation')
                        ->simpleTitle('Stock on hand')
                        ->route('admin.inventory.reports.valuation')
                        ->can('Inventory Report View'),
                    SidebarItem::make('Stock Card')
                        ->simpleTitle('Item movements')
                        ->route('admin.inventory.reports.stock-card')
                        ->can('Inventory Report View'),
                    SidebarItem::make('Warehouses')
                        ->route('admin.inventory.warehouses.index')
                        ->can('Warehouse View List')
                        ->advanced(),
                    SidebarItem::make('Item Categories')
                        ->route('admin.inventory.categories.index')
                        ->can('Item Category View List')
                        ->advanced(),
                    SidebarItem::make('Units')
                        ->route('admin.inventory.units.index')
                        ->can('Unit View List')
                        ->advanced(),
                ),
        );
    }

    protected function dashboard(Dashboard $dashboard): void
    {
        $dashboard->shortcuts(
            DashboardShortcut::make('Add an item')
                ->description('A product you sell or buy, with its price.')
                ->icon('box')
                ->tone('brand')
                ->route('admin.inventory.items.create')
                ->can('Item Create')
                ->sort(70),
            DashboardShortcut::make('Stock on hand')
                ->description('How much of each item you have and what it is worth.')
                ->icon('box-minimalistic')
                ->tone('info')
                ->route('admin.inventory.reports.valuation')
                ->can('Inventory Report View')
                ->sort(80),
        );

        $dashboard->stats(function (User $user): array {
            if (! $user->can('Item View List')) {
                return [];
            }

            $lowStock = Item::query()->where('is_active', true)->lowStock()->count();

            if ($lowStock === 0) {
                return [];
            }

            return [
                DashboardStat::make('Items running low')
                    ->value($lowStock)
                    ->hint('Items at or below their reorder level. Time to buy more.')
                    ->icon('box')
                    ->tone('warning')
                    ->route('admin.inventory.items.index', ['filter' => ['low_stock' => '1']])
                    ->sort(70),
            ];
        });
    }
}
