<?php

namespace App\Modules\Core\Providers;

use App\Models\User;
use App\Modules\Core\Console\Commands\SyncPermissionsCommand;
use App\Modules\Core\Enums\Permissions\ActivityLogPermission;
use App\Modules\Core\Enums\Permissions\DashboardPermission;
use App\Modules\Core\Enums\Permissions\MediaPermission;
use App\Modules\Core\Enums\Permissions\RolePermission;
use App\Modules\Core\Enums\Permissions\SettingPermission;
use App\Modules\Core\Enums\Permissions\UserPermission;
use App\Modules\Core\Policies\MediaPolicy;
use App\Modules\Core\Policies\RolePolicy;
use App\Modules\Core\Policies\UserPolicy;
use App\Modules\Core\Support\ModuleServiceProvider;
use App\Modules\Core\Support\Permissions\PermissionRegistry;
use App\Modules\Core\Support\Sidebar\Sidebar;
use App\Modules\Core\Support\Sidebar\SidebarGroup;
use App\Modules\Core\Support\Sidebar\SidebarItem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;

class CoreServiceProvider extends ModuleServiceProvider
{
    protected array $permissions = [
        DashboardPermission::class,
        UserPermission::class,
        RolePermission::class,
        MediaPermission::class,
        SettingPermission::class,
        ActivityLogPermission::class,
    ];

    protected array $policies = [
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        Media::class => MediaPolicy::class,
    ];

    protected array $routes = [
        'routes/web.php',
        'routes/admin.php',
    ];

    public function register(): void
    {
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(Sidebar::class);
    }

    public function boot(): void
    {
        $this->configureLocalizedUrls();

        parent::boot();

        $this->commands([SyncPermissionsCommand::class]);

        // Super admins pass every check that a policy or gate did not explicitly deny.
        Gate::after(fn (User $user, string $ability, ?bool $result): ?bool => $result ?? ($user->isSuperAdmin() ?: null));
    }

    /**
     * Pages live under a `{locale}` segment, e.g. /ar/admin. Only supported
     * locales match the segment, and URLs generated outside a request, such as
     * in queued jobs or the console, fall back to the default locale.
     */
    protected function configureLocalizedUrls(): void
    {
        $locales = array_keys(config()->array('laravellocalization.supportedLocales'));

        Route::pattern('locale', implode('|', $locales));

        URL::defaults(['locale' => config('app.locale')]);
    }

    protected function modulePath(string $path = ''): string
    {
        return dirname(__DIR__).($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }

    protected function sidebar(Sidebar $sidebar): void
    {
        $sidebar->section('main', 'Menu', sort: 10)->add(
            SidebarItem::make('Dashboard')
                ->icon('layout-dashboard')
                ->route('admin.dashboard')
                ->sort(1),
        );

        $sidebar->section('system', 'System', sort: 90)->add(
            SidebarGroup::make('Users Management')
                ->icon('users')
                ->sort(10)
                ->add(
                    SidebarItem::make('Users')
                        ->route('admin.users.index')
                        ->activeWhen('admin.users.edit')
                        ->can(UserPermission::ViewAny),
                    SidebarItem::make('Add User')
                        ->route('admin.users.create')
                        ->can(UserPermission::Create),
                    SidebarItem::make('Roles & Permissions')
                        ->route('admin.roles.index')
                        ->activeWhen('admin.roles.create', 'admin.roles.edit')
                        ->can(RolePermission::ViewAny),
                ),
            SidebarItem::make('Media Library')
                ->icon('images')
                ->route('admin.media.index')
                ->can(MediaPermission::ViewAny)
                ->sort(15),
            SidebarItem::make('Activity Log')
                ->icon('history')
                ->route('admin.activity-log.index')
                ->can(ActivityLogPermission::ViewAny)
                ->sort(20),
            SidebarItem::make('Settings')
                ->icon('settings')
                ->route('admin.settings.edit')
                ->can(SettingPermission::View)
                ->sort(30),
        );
    }
}
