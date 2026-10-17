<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Contracts\PermissionGroup;
use App\Modules\Core\Support\Permissions\PermissionRegistry;
use App\Modules\Core\Support\Sidebar\Sidebar;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * The base provider every module extends.
 *
 * A module declares its permissions, policies, routes and sidebar entries here.
 * Permissions and sidebar entries are registered lazily, only when the registry
 * or the sidebar is actually resolved.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * The permission group enums the module defines.
     *
     * @var list<class-string<PermissionGroup>>
     */
    protected array $permissions = [];

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

    public function boot(): void
    {
        $this->callAfterResolving(PermissionRegistry::class, fn (PermissionRegistry $registry) => $registry->register(...$this->permissions));
        $this->callAfterResolving(Sidebar::class, fn (Sidebar $sidebar) => $this->sidebar($sidebar));

        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        foreach ($this->routes as $routes) {
            $this->loadRoutesFrom($this->modulePath($routes));
        }
    }
}
