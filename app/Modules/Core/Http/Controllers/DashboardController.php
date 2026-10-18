<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Http\Resources\ActivityResource;
use App\Modules\Core\Http\Resources\UserResource;
use App\Modules\Core\Support\Dashboard\Dashboard;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard: the quick actions and key figures of the
     * installed modules, then the users overview.
     */
    public function __invoke(Request $request, Dashboard $dashboard): Response
    {
        return Inertia::render('admin/Dashboard', [
            'shortcuts' => $dashboard->resolveShortcuts($request),
            'businessStats' => Inertia::defer(fn (): array => $dashboard->resolveStats($request), 'business'),
            'stats' => $this->stats(),
            'registrations' => Inertia::defer(fn (): array => $this->registrationsPerMonth()),
            'recentUsers' => UserResource::collection(User::query()->with('roles')->latest()->limit(5)->get()),
            'recentActivity' => ActivityResource::collection(Activity::query()->with('causer')->latest('id')->limit(6)->get()),
        ]);
    }

    /**
     * @return array{users: int, newUsers: int, newUsersChange: float|null, roles: int, activityToday: int}
     */
    protected function stats(): array
    {
        $newUsers = User::query()->where('created_at', '>=', now()->startOfMonth())->count();

        $previousNewUsers = User::query()
            ->whereBetween('created_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])
            ->count();

        return [
            'users' => User::query()->count(),
            'newUsers' => $newUsers,
            'newUsersChange' => $previousNewUsers > 0 ? round(($newUsers - $previousNewUsers) / $previousNewUsers * 100, 1) : null,
            'roles' => Role::query()->count(),
            'activityToday' => Activity::query()->where('created_at', '>=', today())->count(),
        ];
    }

    /**
     * @return list<array{month: string, total: int}>
     */
    protected function registrationsPerMonth(): array
    {
        $registrations = Trend::model(User::class)
            ->between(start: now()->subMonths(11)->startOfMonth(), end: now()->endOfMonth())
            ->perMonth()
            ->count()
            ->map(fn (TrendValue $value): array => ['month' => $value->date, 'total' => (int) $value->aggregate]);

        return array_values($registrations->all());
    }
}
