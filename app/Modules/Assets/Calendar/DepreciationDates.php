<?php

namespace App\Modules\Assets\Calendar;

use App\Models\User;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Foundation\Support\Calendar\CalendarEntry;
use App\Modules\Foundation\Support\Calendar\CalendarSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The monthly depreciation: each month end that still has active assets
 * not depreciated up to it. A past month end left behind is overdue.
 */
class DepreciationDates extends CalendarSource
{
    public function key(): string
    {
        return 'depreciation';
    }

    public function label(): string
    {
        return __('Monthly depreciation');
    }

    public function color(): string
    {
        return 'gray';
    }

    public function permission(): string
    {
        return 'Asset View List';
    }

    public function entries(CarbonInterface $from, CarbonInterface $to, User $user): iterable
    {
        $today = now()->startOfDay();
        $entries = [];
        $month = Carbon::parse($from)->startOfMonth();

        while ($month->lessThanOrEqualTo($to)) {
            $monthEnd = $month->copy()->endOfMonth()->startOfDay();

            if ($monthEnd->between($from, $to) && $this->isDue($monthEnd)) {
                $entries[] = $this->entry($monthEnd, $monthEnd->lessThan($today));
            }

            $month = $month->addMonth();
        }

        return $entries;
    }

    public function overdue(CarbonInterface $today, User $user): iterable
    {
        $lastMonthEnd = Carbon::parse($today)->startOfMonth()->subDay()->startOfDay();

        return $this->isDue($lastMonthEnd) ? [$this->entry($lastMonthEnd, true)] : [];
    }

    /**
     * Whether some active asset is not yet depreciated up to the month end.
     */
    protected function isDue(CarbonInterface $monthEnd): bool
    {
        return Asset::query()
            ->where('status', AssetStatus::Active)
            ->whereDate('depreciation_start_date', '<=', $monthEnd->toDateString())
            ->where(fn ($query) => $query->whereNull('depreciated_until')->orWhereDate('depreciated_until', '<', $monthEnd->toDateString()))
            ->exists();
    }

    protected function entry(CarbonInterface $monthEnd, bool $overdue): CalendarEntry
    {
        return new CalendarEntry(
            id: "depreciation-{$monthEnd->format('Y-m')}",
            type: $this->key(),
            date: $monthEnd,
            title: __('Depreciation of :month', ['month' => $monthEnd->format('Y-m')]),
            url: route('admin.assets.depreciation.index'),
            status: __('Not run yet'),
            color: $overdue ? 'error' : null,
            overdue: $overdue,
        );
    }
}
