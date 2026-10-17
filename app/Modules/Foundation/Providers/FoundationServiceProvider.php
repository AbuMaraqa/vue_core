<?php

namespace App\Modules\Foundation\Providers;

use App\Modules\Core\Support\ModuleServiceProvider;
use App\Modules\Core\Support\Sidebar\Sidebar;
use App\Modules\Core\Support\Sidebar\SidebarItem;
use App\Modules\Foundation\Console\Commands\MakeModuleCommand;
use App\Modules\Foundation\Exceptions\ExceptionResponder;
use App\Modules\Foundation\Http\Middleware\AddSecurityHeaders;
use App\Modules\Foundation\Http\Middleware\AssignRequestId;
use App\Modules\Foundation\Listeners\NotifyStaffOfBackups;
use App\Modules\Foundation\Listeners\NotifyStaffOfNewUser;
use App\Modules\Foundation\Listeners\RecordAuthenticationEvents;
use App\Modules\Foundation\Models\AuthenticationLog;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * The Foundation module: cross-cutting services every module relies on.
 *
 * It builds on Core's module system, and business modules build on both.
 * Core never depends on Foundation.
 */
class FoundationServiceProvider extends ModuleServiceProvider
{
    protected array $routes = [
        'routes/health.php',
        'routes/web.php',
        'routes/admin.php',
        'routes/api.php',
    ];

    public function boot(): void
    {
        parent::boot();

        $this->loadViewsFrom($this->modulePath('resources/views'), 'foundation');

        if ($this->app->runningInConsole()) {
            $this->commands([MakeModuleCommand::class]);
        }

        $this->registerGlobalMiddleware();
        $this->configureRateLimiting();

        Inertia::handleExceptionsUsing(new ExceptionResponder);

        Event::subscribe(RecordAuthenticationEvents::class);
        Event::listen(Registered::class, NotifyStaffOfNewUser::class);
        Event::subscribe(NotifyStaffOfBackups::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('model:prune', ['--model' => [AuthenticationLog::class]])->daily();
        });
    }

    protected function modulePath(string $path = ''): string
    {
        return dirname(__DIR__).($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }

    protected function sidebar(Sidebar $sidebar): void
    {
        $sidebar->section('system')->add(
            SidebarItem::make('Login History')
                ->icon('shield-user')
                ->route('admin.login-history.index')
                ->can('Login History View List')
                ->sort(22),
            SidebarItem::make('Backups')
                ->icon('database')
                ->route('admin.backups.index')
                ->can('Backup View List')
                ->sort(25),
        );
    }

    /**
     * API requests are limited per user, or per IP address before signing in.
     * Signing in is limited per email and IP address against password guessing.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(config()->integer('foundation.api.rate_limit'))
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('api-token', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(Str::lower($request->string('email')->value()).'|'.$request->ip()));
    }

    /**
     * The request id comes first, so everything after it, including errors,
     * can be traced. Security headers are added to every response.
     */
    protected function registerGlobalMiddleware(): void
    {
        $kernel = $this->app->make(Kernel::class);

        $kernel->prependMiddleware(AssignRequestId::class);
        $kernel->pushMiddleware(AddSecurityHeaders::class);
    }
}
