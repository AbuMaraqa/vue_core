<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Enums\Permissions\ActivityLogPermission;
use App\Modules\Core\Http\Resources\ActivityResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ActivityLogController extends Controller
{
    /**
     * Display the audit trail of changes made in the system.
     */
    public function __invoke(Request $request): Response
    {
        Gate::authorize(ActivityLogPermission::ViewAny);

        $activities = QueryBuilder::for(Activity::query()->with('causer'))
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $query, string $search) => $query->where('description', 'like', "%{$search}%"))->delimiter(''),
                AllowedFilter::exact('event'),
                AllowedFilter::exact('log_name'),
            )
            ->defaultSort('-id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/activity-log/Index', [
            'activities' => ActivityResource::collection($activities),
            'logNames' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name')->filter()->values(),
            'filters' => [
                'search' => $request->input('filter.search'),
                'event' => $request->input('filter.event'),
                'log_name' => $request->input('filter.log_name'),
            ],
        ]);
    }
}
