<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Settings\LocalizationSettings;
use App\Modules\Core\Support\Localization\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(protected Locales $locales) {}

    /**
     * Apply the locale chosen by the visitor, their account, or the store default.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->locales->apply($this->resolveLocale($request));

        return $next($request);
    }

    protected function resolveLocale(Request $request): string
    {
        $candidates = [
            $request->session()->get('locale'),
            $request->user()?->locale,
            app(LocalizationSettings::class)->default_locale,
        ];

        foreach ($candidates as $locale) {
            if ($this->locales->isSupported($locale)) {
                return $locale;
            }
        }

        return config('app.locale');
    }
}
