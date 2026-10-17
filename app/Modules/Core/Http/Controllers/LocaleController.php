<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\UpdateLocaleRequest;
use App\Modules\Core\Support\Localization\Locales;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class LocaleController extends Controller
{
    /**
     * Switch the interface language, remember it for the signed-in user, and
     * reload the current page in that language.
     */
    public function __invoke(UpdateLocaleRequest $request, Locales $locales): Response
    {
        $locale = $request->validated('locale');

        $request->session()->put('locale', $locale);
        $request->user()?->update(['locale' => $locale]);

        return Inertia::location($this->localizedPreviousUrl($locales, $locale));
    }

    /**
     * Swap the language segment of the previous page. Only its path is reused,
     * so the redirect always stays on this site.
     */
    protected function localizedPreviousUrl(Locales $locales, string $locale): string
    {
        $previous = parse_url(url()->previous());
        $segments = explode('/', trim($previous['path'] ?? '', '/'));

        if ($locales->isSupported($segments[0])) {
            $segments[0] = $locale;
        }

        $url = url(implode('/', $segments));

        return isset($previous['query']) ? "{$url}?{$previous['query']}" : $url;
    }
}
