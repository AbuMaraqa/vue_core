<?php

namespace App\Modules\Core\Support\Dashboard;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * The admin dashboard registry.
 *
 * Modules add quick actions (big buttons for everyday tasks) and key figures
 * while booting, so the dashboard shows what each installed module offers
 * without Core depending on it. Both are resolved per request for the
 * current user.
 */
class Dashboard
{
    /**
     * @var list<DashboardShortcut>
     */
    protected array $shortcuts = [];

    /**
     * @var list<Closure(User): list<DashboardStat>>
     */
    protected array $stats = [];

    public function shortcuts(DashboardShortcut ...$shortcuts): static
    {
        array_push($this->shortcuts, ...$shortcuts);

        return $this;
    }

    /**
     * Add key figures. The callback runs only when the dashboard needs them,
     * so it can compute several figures at once and skip those the user may not see.
     *
     * @param  Closure(User): list<DashboardStat>  $stats
     */
    public function stats(Closure $stats): static
    {
        $this->stats[] = $stats;

        return $this;
    }

    /**
     * @return list<array{key: string, title: string, description: string|null, icon: string|null, tone: string, url: string}>
     */
    public function resolveShortcuts(Request $request): array
    {
        return array_values(collect($this->shortcuts)
            ->filter(fn (DashboardShortcut $shortcut): bool => $shortcut->isAuthorized($request->user()))
            ->sortBy(fn (DashboardShortcut $shortcut): int => $shortcut->getSort())
            ->map(fn (DashboardShortcut $shortcut): array => $shortcut->toArray())
            ->all());
    }

    /**
     * @return list<array{key: string, label: string, value: string, currency: string|null, decimals: int, hint: string|null, icon: string|null, tone: string, url: string|null}>
     */
    public function resolveStats(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return array_values(collect($this->stats)
            ->flatMap(fn (Closure $stats): array => $stats($user))
            ->sortBy(fn (DashboardStat $stat): int => $stat->getSort())
            ->map(fn (DashboardStat $stat): array => $stat->toArray())
            ->all());
    }
}
