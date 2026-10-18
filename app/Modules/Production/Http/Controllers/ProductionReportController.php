<?php

namespace App\Modules\Production\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Controllers\Concerns\RendersReports;
use App\Modules\Foundation\Exports\Report;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Support\InventoryOptions;
use App\Modules\Production\Reports\ProductionSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * The production report: one GET route that renders the page or downloads
 * it with `?format=`.
 */
class ProductionReportController extends Controller
{
    use RendersReports;

    public function summary(Request $request, ProductionSummary $report, InventoryOptions $options): Response
    {
        Gate::authorize('Production Report View');

        [$from, $to] = $this->reportPeriod($request, defaultFromStartOfMonth: true);
        $item = Item::query()->with('unit')->find($request->integer('item_id'));

        return $this->report(
            $request,
            'admin/production/reports/Summary',
            [
                'report' => $report->generate($from, $to, $item?->id),
                'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'item_id' => $item?->id],
                'selectedItem' => $item ? $options->itemOption($item) : null,
            ],
            fn (): Report => $report->export($from, $to, $item?->id),
            exportPermission: 'Production Report Export',
        );
    }
}
