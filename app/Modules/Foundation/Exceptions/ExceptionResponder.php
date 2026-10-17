<?php

namespace App\Modules\Foundation\Exceptions;

use App\Modules\Foundation\Http\Middleware\AssignRequestId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Context;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Turn error responses into something a user (or an API client) can act on.
 *
 * - API / JSON requests keep Laravel's JSON error and gain the `request_id`.
 * - An expired page (419) sends the user back with a toast instead of an error screen.
 * - 403, 404, 429, 500 and 503 render the translated `errors/Error` page. In debug
 *   mode a 500 still shows the detailed exception page for developers.
 */
class ExceptionResponder
{
    /**
     * Statuses rendered with the error page.
     *
     * @var list<int>
     */
    public const array PAGE_STATUSES = [403, 404, 429, 500, 503];

    public function __invoke(ExceptionResponse $exception): ?Response
    {
        $request = $exception->request;
        $status = $exception->statusCode();

        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->withRequestId($exception->response);
        }

        if ($status === 419) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => __('The page expired, please try again.')]);

            return back();
        }

        if (! in_array($status, self::PAGE_STATUSES, true) || ($status === 500 && config('app.debug'))) {
            return null;
        }

        return $this->errorPage($exception, $status);
    }

    /**
     * Render the error page, falling back to the plain response when the page
     * itself cannot be rendered, e.g. because the database is down.
     */
    protected function errorPage(ExceptionResponse $exception, int $status): ?Response
    {
        try {
            return $exception
                ->render('errors/Error', [
                    'status' => $status,
                    'requestId' => Context::get(AssignRequestId::CONTEXT_KEY),
                ])
                ->withSharedData()
                ->toResponse($exception->request);
        } catch (Throwable $renderingError) {
            report($renderingError);

            return null;
        }
    }

    protected function withRequestId(Response $response): Response
    {
        $requestId = Context::get(AssignRequestId::CONTEXT_KEY);

        if ($response instanceof JsonResponse && $requestId !== null) {
            $data = $response->getData(true);

            if (is_array($data)) {
                $response->setData([...$data, 'request_id' => $requestId]);
            }
        }

        return $response;
    }
}
