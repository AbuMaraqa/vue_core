<?php

namespace App\Modules\Assets\Providers;

use App\Modules\Accounting\Events\BaseCurrencyChanged;
use App\Modules\Accounting\Support\DocumentLinks;
use App\Modules\Assets\Calendar\DepreciationDates;
use App\Modules\Assets\Listeners\ConvertAssetsToNewBaseCurrency;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetCategory;
use App\Modules\Assets\Models\DepreciationRun;
use App\Modules\Assets\Policies\AssetCategoryPolicy;
use App\Modules\Assets\Policies\AssetPolicy;
use App\Modules\Assets\Policies\DepreciationRunPolicy;
use App\Modules\Core\Support\ModuleServiceProvider;
use App\Modules\Core\Support\Sidebar\Sidebar;
use App\Modules\Core\Support\Sidebar\SidebarGroup;
use App\Modules\Core\Support\Sidebar\SidebarItem;
use App\Modules\Foundation\Support\Calendar\Calendar;
use Illuminate\Support\Facades\Event;

/**
 * The Assets module: the fixed asset register, monthly depreciation and
 * disposals, posted through the Accounting ledger.
 */
class AssetsServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Asset::class => AssetPolicy::class,
        AssetCategory::class => AssetCategoryPolicy::class,
        DepreciationRun::class => DepreciationRunPolicy::class,
    ];

    protected array $routes = [
        'routes/admin.php',
    ];

    public function boot(): void
    {
        parent::boot();

        $this->app->make(Calendar::class)->register(DepreciationDates::class);

        $this->loadViewsFrom($this->modulePath('resources/views'), 'assets');

        Event::listen(BaseCurrencyChanged::class, ConvertAssetsToNewBaseCurrency::class);

        $links = $this->app->make(DocumentLinks::class);
        $links->register(Asset::class, fn (Asset $asset): array => [
            'label' => __('Fixed asset'),
            'url' => route('admin.assets.assets.show', $asset),
        ]);
        $links->register(DepreciationRun::class, fn (DepreciationRun $run): array => [
            'label' => __('Depreciation'),
            'url' => route('admin.assets.depreciation.show', $run),
        ]);
    }

    protected function modulePath(string $path = ''): string
    {
        return dirname(__DIR__).($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }

    protected function sidebar(Sidebar $sidebar): void
    {
        $sidebar->section('accounting', 'Accounting', sort: 30)->add(
            SidebarGroup::make('Fixed Assets')
                ->advanced()
                ->icon('buildings')
                ->sort(50)
                ->add(
                    SidebarItem::make('Assets')
                        ->route('admin.assets.assets.index')
                        ->activeWhen('admin.assets.assets.*')
                        ->can('Asset View List'),
                    SidebarItem::make('Depreciation')
                        ->route('admin.assets.depreciation.index')
                        ->activeWhen('admin.assets.depreciation.*')
                        ->can('Depreciation Run View List'),
                    SidebarItem::make('Asset Register')
                        ->route('admin.assets.reports.register')
                        ->can('Asset View List'),
                    SidebarItem::make('Asset Categories')
                        ->route('admin.assets.categories.index')
                        ->can('Asset Category View List'),
                ),
        );
    }
}
