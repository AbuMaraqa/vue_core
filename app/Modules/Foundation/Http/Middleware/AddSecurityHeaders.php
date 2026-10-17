<?php

namespace App\Modules\Foundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add the browser security headers configured in `foundation.security`.
 */
class AddSecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        /** @var array<string, string|null> $headers */
        $headers = config()->array('foundation.security.headers');

        foreach ($headers as $name => $value) {
            if ($value !== null && ! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($request->isSecure() && config()->boolean('foundation.security.hsts.enabled')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.config()->integer('foundation.security.hsts.max_age').'; includeSubDomains',
            );
        }

        return $response;
    }
}
