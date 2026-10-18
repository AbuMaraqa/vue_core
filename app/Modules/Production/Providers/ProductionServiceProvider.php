<?php

namespace App\Modules\Production\Providers;

use App\Modules\Accounting\Events\BaseCurrencyChanged;
use App\Modules\Accounting\Support\DocumentLinks;
use App\Modules\Core\Support\ModuleServiceProvider;
use App\Modules\Core\Support\Sidebar\Sidebar;
use App\Modules\Core\Support\Sidebar\SidebarGroup;
use App\Modules\Core\Support\Sidebar\SidebarItem;
use App\Modules\Foundation\Support\Calendar\Calendar;
use App\Modules\Production\Calendar\ProductionOrderDates;
use App\Modules\Production\Listeners\ConvertProductionToNewBaseCurrency;
use App\Modules\Production\Models\BillOfMaterials;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Policies\BillOfMaterialsPolicy;
use App\Modules\Production\Policies\ProductionOrderPolicy;
use Illuminate\Support\Facades\Event;

/**
 * The Production module: recipes (bills of materials) and production orders
 * that turn materials into finished products, e.g. wood, hinges and paint
 * into a wardrobe. Stock moves through the Inventory module's StockLedger
 * and the cost is posted through the Accounting module, via work in
 * progress.
 *
 * A business that does not manufacture can leave this provider out of
 * `bootstrap/providers.php`; nothing else depends on it.
 */
class ProductionServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        BillOfMaterials::class => BillOfMaterialsPolicy::class,
        ProductionOrder::class => ProductionOrderPolicy::class,
    ];

    protected array $routes = [
        'routes/admin.php',
    ];

    public function boot(): void
    {
        parent::boot();

        $this->app->make(Calendar::class)->register(ProductionOrderDates::class);

        $this->loadViewsFrom($this->modulePath('resources/views'), 'production');

        Event::listen(BaseCurrencyChanged::class, ConvertProductionToNewBaseCurrency::class);

        $this->app->make(DocumentLinks::class)->register(ProductionOrder::class, fn (ProductionOrder $order): array => [
            'label' => __('Production order'),
            'url' => route('admin.production.orders.show', $order),
        ]);
    }

    protected function modulePath(string $path = ''): string
    {
        return dirname(__DIR__).($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }

    protected function sidebar(Sidebar $sidebar): void
    {
        $sidebar->section('accounting', 'Accounting', sort: 30)->add(
            SidebarGroup::make('Production')
                ->icon('production')
                ->sort(45)
                ->add(
                    SidebarItem::make('Production Orders')
                        ->route('admin.production.orders.index')
                        ->activeWhen('admin.production.orders.*')
                        ->can('Production Order View List'),
                    SidebarItem::make('Bills of Materials')
                        ->route('admin.production.boms.index')
                        ->activeWhen('admin.production.boms.*')
                        ->can('Bill Of Materials View List'),
                    SidebarItem::make('Production Report')
                        ->route('admin.production.reports.summary')
                        ->can('Production Report View'),
                ),
        );
    }
}
