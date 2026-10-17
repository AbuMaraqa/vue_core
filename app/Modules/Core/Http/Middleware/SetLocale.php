<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Settings\LocalizationSettings;
use App\Modules\Core\Support\Localization\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(protected Locales $locales) {}

    /**
     * Apply the locale of the URL, or the one chosen by the visitor, their
     * account or the store default on pages without a language segment.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $urlLocale = $request->route('locale');

        if (is_string($urlLocale) && $this->locales->isSupported($urlLocale)) {
            $request->session()->put('locale', $urlLocale);

            // The segment only selects the language; controllers never receive it.
            $request->route()?->forgetParameter('locale');
        }

        $locale = $this->resolveLocale($request);

        $this->locales->apply($locale);

        URL::defaults(['locale' => $locale]);

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
