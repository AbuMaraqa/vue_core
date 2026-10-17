<?php

namespace App\Modules\Foundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Give every request an id that follows it everywhere: log entries, queued
 * jobs dispatched by the request (both through Laravel's Context), the
 * `X-Request-Id` response header and the error page. Support can then find
 * every trace of a problem a user reports.
 */
class AssignRequestId
{
    public const string HEADER = 'X-Request-Id';

    public const string CONTEXT_KEY = 'request_id';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->incomingRequestId($request) ?? (string) Str::uuid();

        Context::add(self::CONTEXT_KEY, $requestId);

        $response = $next($request);

        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    /**
     * Reuse the id set by a load balancer or a calling service, as long as it
     * is a plain token that is safe to write to the logs.
     */
    protected function incomingRequestId(Request $request): ?string
    {
        $requestId = $request->headers->get(self::HEADER);

        return is_string($requestId) && preg_match('/^[A-Za-z0-9._-]{8,128}$/', $requestId) === 1
            ? $requestId
            : null;
    }
}
