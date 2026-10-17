<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Modules\Core\Models\Site;
use App\Modules\Core\Settings\AppearanceSettings;
use App\Modules\Core\Settings\GeneralSettings;
use App\Modules\Core\Support\Localization\Locales;
use App\Modules\Core\Support\Sidebar\Sidebar;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $locales = app(Locales::class);

        return [
            ...parent::share($request),
            'name' => fn (): string => app(GeneralSettings::class)->site_name,
            'theme' => fn (): array => [
                ...app(AppearanceSettings::class)->theme(),
                'logoUrl' => Site::current()->logoUrl(),
            ],
            'auth' => [
                'user' => $user ? $this->sharedUser($user) : null,
                'permissions' => fn (): array => $user?->permissionNames() ?? [],
            ],
            'unreadNotifications' => fn (): int => $user?->unreadNotifications()->count() ?? 0,
            'locale' => app()->getLocale(),
            'direction' => $locales->direction(),
            'locales' => fn (): array => $locales->all(),
            'translations' => fn (): array => $locales->translations(),
            'sidebar' => fn (): array => $user ? app(Sidebar::class)->resolve($request) : [],
        ];
    }

    /**
     * Get the fields of the signed-in user every page may use.
     *
     * The fields are listed explicitly, so columns added to the users table
     * later are never sent to the browser by accident.
     *
     * @return array{id: int, name: string, email: string, locale: string|null, email_verified_at: string|null}
     */
    protected function sharedUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ];
    }
}
