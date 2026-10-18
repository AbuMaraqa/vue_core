<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Support\Dashboard\Dashboard;
use App\Modules\Core\Support\Sidebar\Sidebar;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * The base provider every module extends.
 *
 * A module declares its policies, routes, sidebar entries and dashboard
 * shortcuts here. Sidebar and dashboard entries are registered lazily, only
 * when they are actually resolved.
 * The permissions of every module are defined in the PermissionSeeder.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings of the module.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [];

    /**
     * The route files of the module, relative to the module root.
     *
     * @var list<string>
     */
    protected array $routes = [];

    /**
     * Get the absolute path of the module root directory.
     */
    abstract protected function modulePath(string $path = ''): string;

    /**
     * Describe the module navigation.
     */
    protected function sidebar(Sidebar $sidebar): void
    {
        //
    }

    /**
     * Describe the quick actions and key figures the module adds to the dashboard.
     */
    protected function dashboard(Dashboard $dashboard): void
    {
        //
    }

    public function boot(): void
    {
        $this->callAfterResolving(Sidebar::class, fn (Sidebar $sidebar) => $this->sidebar($sidebar));
        $this->callAfterResolving(Dashboard::class, fn (Dashboard $dashboard) => $this->dashboard($dashboard));

        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        foreach ($this->routes as $routes) {
            $this->loadRoutesFrom($this->modulePath($routes));
        }
    }
}
