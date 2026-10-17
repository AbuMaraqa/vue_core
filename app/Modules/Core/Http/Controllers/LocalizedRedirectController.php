<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Send a link without a language segment to the same page in the visitor's language.
 */
class LocalizedRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $path = trim($request->path(), '/');
        $target = '/'.app()->getLocale().($path === '' ? '' : "/{$path}");

        abort_unless($this->routeExists($target), 404);

        $query = $request->getQueryString();

        return redirect($query ? "{$target}?{$query}" : $target);
    }

    protected function routeExists(string $path): bool
    {
        return ! Route::getRoutes()->match(Request::create($path))->isFallback;
    }
}
