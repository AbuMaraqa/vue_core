<?php

namespace App\Modules\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Foundation\Enums\AuthenticationEvent;
use App\Modules\Foundation\Exports\LoginHistoryExporter;
use App\Modules\Foundation\Http\Requests\ExportRequest;
use App\Modules\Foundation\Http\Resources\AuthenticationLogResource;
use App\Modules\Foundation\Models\AuthenticationLog;
use App\Modules\Foundation\Support\Filters\SearchFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Every sign-in, sign-out, failed attempt and lockout, for security reviews.
 */
class LoginHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('Login History View List');

        return Inertia::render('admin/login-history/Index', [
            'logs' => AuthenticationLogResource::collection($this->query()->paginate(20)->withQueryString()),
            'events' => AuthenticationEvent::options(),
            'filters' => [
                'search' => $request->input('filter.search'),
                'event' => $request->input('filter.event'),
            ],
        ]);
    }

    /**
     * The permission is checked by the route, before the format is validated.
     */
    public function export(ExportRequest $request): SymfonyResponse
    {
        return LoginHistoryExporter::from($this->query())->download($request->exportFormat());
    }

    /**
     * The filtered history, shared by the page and its exports.
     *
     * @return QueryBuilder<AuthenticationLog>
     */
    protected function query(): QueryBuilder
    {
        return QueryBuilder::for(AuthenticationLog::query()->with('user'))
            ->allowedFilters(
                SearchFilter::on('email', 'ip_address'),
                AllowedFilter::exact('event'),
            )
            ->defaultSort('-id');
    }
}
