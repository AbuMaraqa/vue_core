<?php

namespace App\Modules\Assets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Assets\Actions\CancelDepreciationRun;
use App\Modules\Assets\Actions\RunDepreciation;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetDepreciation;
use App\Modules\Assets\Models\DepreciationRun;
use App\Modules\Assets\Printing\DepreciationRunDocument;
use App\Modules\Assets\Support\DepreciationSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monthly depreciation runs: a preview of what the next run will post, the
 * past runs, and cancelling the latest one.
 */
class DepreciationRunController extends Controller
{
    public function index(Request $request, DepreciationSchedule $schedule, Currencies $currencies): Response
    {
        Gate::authorize('viewAny', DepreciationRun::class);

        $month = Carbon::parse($request->input('month', now()->format('Y-m')).'-01')->endOfMonth();

        $preview = Asset::query()
            ->where('status', AssetStatus::Active)
            ->whereDate('depreciation_start_date', '<=', $month->toDateString())
            ->orderBy('number')
            ->get()
            ->map(fn (Asset $asset): array => ['id' => $asset->id, 'number' => $asset->number, 'name' => $asset->name, ...$schedule->due($asset, $month)])
            ->filter(fn (array $row): bool => Decimal::isPositive($row['amount']))
            ->values();

        $latestPosted = DepreciationRun::query()->where('status', DocumentStatus::Posted)->latest('period_end')->latest('id')->value('id');

        return Inertia::render('admin/assets/depreciation/Index', [
            'month' => $month->format('Y-m'),
            'preview' => $preview,
            'previewTotal' => Decimal::round(Decimal::sum($preview->pluck('amount'))),
            'runs' => DepreciationRun::query()->with('creator')->withCount('depreciations')->latest('period_end')->latest('id')->paginate(12)->through(fn (DepreciationRun $run): array => [
                'id' => $run->id,
                'number' => $run->number,
                'period_end' => $run->period_end->toDateString(),
                'total' => (string) $run->total,
                'assets' => $run->getAttribute('depreciations_count'),
                'status' => $run->status->value,
                'status_label' => $run->status->label(),
                'status_color' => $run->status->color(),
                'created_by' => $run->creator?->name,
                'can_cancel' => $run->id === $latestPosted && ($request->user()?->can('cancel', $run) ?? false),
            ]),
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function store(Request $request, RunDepreciation $runDepreciation): RedirectResponse
    {
        Gate::authorize('create', DepreciationRun::class);

        $validated = $request->validate(['month' => ['required', 'date_format:Y-m']]);

        $run = $runDepreciation->handle(Carbon::parse($validated['month'].'-01'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Depreciation :number posted.', ['number' => $run->number])]);

        return to_route('admin.assets.depreciation.show', $run);
    }

    public function show(DepreciationRun $run, Currencies $currencies): Response
    {
        Gate::authorize('view', $run);

        $run->load(['depreciations.asset.category', 'journalEntries', 'creator']);

        return Inertia::render('admin/assets/depreciation/Show', [
            'run' => [
                'id' => $run->id,
                'number' => $run->number,
                'period_end' => $run->period_end->toDateString(),
                'total' => (string) $run->total,
                'status' => $run->status->value,
                'status_label' => $run->status->label(),
                'status_color' => $run->status->color(),
                'created_by' => $run->creator?->name,
                'journal_entries' => $run->journalEntries->map(fn ($entry): array => ['id' => $entry->id, 'number' => $entry->number])->values(),
                'lines' => $run->depreciations->map(fn (AssetDepreciation $depreciation): array => [
                    'asset_id' => $depreciation->asset_id,
                    'asset' => "{$depreciation->asset->number} {$depreciation->asset->name}",
                    'category' => $depreciation->asset->category->translate('name'),
                    'from_date' => $depreciation->from_date->toDateString(),
                    'to_date' => $depreciation->to_date->toDateString(),
                    'months' => $depreciation->months,
                    'amount' => (string) $depreciation->amount,
                ])->values(),
            ],
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    /**
     * The printable depreciation run.
     */
    public function pdf(DepreciationRun $run): DepreciationRunDocument
    {
        Gate::authorize('view', $run);

        return new DepreciationRunDocument($run);
    }

    public function cancel(DepreciationRun $run, CancelDepreciationRun $cancelRun): RedirectResponse
    {
        Gate::authorize('cancel', $run);

        $cancelRun->handle($run);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Depreciation run cancelled.')]);

        return to_route('admin.assets.depreciation.index');
    }
}
