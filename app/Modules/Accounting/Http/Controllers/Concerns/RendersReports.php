<?php

namespace App\Modules\Accounting\Http\Controllers\Concerns;

use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Foundation\Exports\ExportFormat;
use App\Modules\Foundation\Exports\Report;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reports of every financial module share one shape: a single GET route that
 * renders the page, or downloads the same report when `?format=xlsx|csv|pdf`
 * is given. The caller authorizes viewing; downloading also needs the export
 * permission.
 */
trait RendersReports
{
    /**
     * @param  Closure(): Report  $export
     * @param  array<string, mixed>  $props
     */
    protected function report(Request $request, string $component, array $props, Closure $export, string $exportPermission = 'Financial Report Export'): Response
    {
        $format = $request->enum('format', ExportFormat::class);

        if ($format !== null) {
            Gate::authorize($exportPermission);

            return $export()->download($format);
        }

        return $this->reportPage($component, $props);
    }

    /**
     * Render a report page with the base currency, used to format amounts.
     *
     * @param  array<string, mixed>  $props
     */
    protected function reportPage(string $component, array $props): Response
    {
        $base = app(Currencies::class)->base();

        return Inertia::render($component, [
            ...$props,
            'baseCurrency' => ['code' => $base->code, 'decimal_places' => $base->decimal_places],
        ])->toResponse(request());
    }

    /**
     * The requested period, by default the current fiscal year up to today.
     * Reversed dates are swapped.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    protected function reportPeriod(Request $request, bool $defaultFromStartOfMonth = false): array
    {
        $defaultFrom = $defaultFromStartOfMonth
            ? now()->startOfMonth()
            : (FiscalYear::query()->containing(now())->value('start_date') ?? now()->startOfYear());

        $from = $request->date('from') ?? Carbon::parse($defaultFrom);
        $to = $request->date('to') ?? now();

        return $from->greaterThan($to) ? [$to->copy()->startOfDay(), $from->copy()->endOfDay()] : [$from->startOfDay(), $to->endOfDay()];
    }
}
