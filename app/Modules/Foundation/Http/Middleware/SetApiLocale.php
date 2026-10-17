<?php

namespace App\Modules\Foundation\Http\Middleware;

use App\Modules\Core\Support\Localization\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answer API requests in the language the app asks for with the
 * `Accept-Language` header, e.g. `ar`, falling back to the default language.
 */
class SetApiLocale
{
    public function __construct(protected Locales $locales) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage($this->locales->codes());

        if ($request->hasHeader('Accept-Language') && $this->locales->isSupported($locale)) {
            $this->locales->apply($locale);
        }

        $response = $next($request);

        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }
}
