<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Controllers\Concerns\RendersReports;
use App\Modules\Foundation\Exports\Report;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Reports\StockCard;
use App\Modules\Inventory\Reports\StockValuation;
use App\Modules\Inventory\Support\InventoryOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inventory reports: stock valuation and the stock card of an item. Each is
 * one GET route that renders the page or downloads it with `?format=`.
 */
class InventoryReportController extends Controller
{
    use RendersReports;

    public function valuation(Request $request, StockValuation $report, InventoryOptions $options): Response
    {
        Gate::authorize('Inventory Report View');

        $asOf = $request->date('as_of') ?? now();
        $warehouse = $request->integer('warehouse_id') ?: null;
        $category = $request->integer('category_id') ?: null;
        $includeZero = $request->boolean('include_zero');

        return $this->report(
            $request,
            'admin/inventory/reports/StockValuation',
            [
                'report' => $report->generate($asOf, $warehouse, $category, $includeZero),
                'filters' => ['as_of' => $asOf->toDateString(), 'warehouse_id' => $warehouse, 'category_id' => $category, 'include_zero' => $includeZero],
                'warehouses' => $options->warehouses(),
                'categories' => $options->categories(),
            ],
            fn (): Report => $report->export($asOf, $warehouse, $category, $includeZero),
            exportPermission: 'Inventory Report Export',
        );
    }

    public function stockCard(Request $request, StockCard $report, InventoryOptions $options): Response
    {
        Gate::authorize('Inventory Report View');

        [$from, $to] = $this->reportPeriod($request);
        $item = Item::query()->with('unit')->find($request->integer('item_id'));
        $warehouse = $request->integer('warehouse_id') ?: null;

        return $this->report(
            $request,
            'admin/inventory/reports/StockCard',
            [
                'report' => $item ? $report->generate($item, $from, $to, $warehouse) : null,
                'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'item_id' => $item?->id, 'warehouse_id' => $warehouse],
                'selectedItem' => $item ? $options->itemOption($item) : null,
                'warehouses' => $options->warehouses(),
            ],
            fn (): Report => $report->export($item ?? abort(422), $from, $to, $warehouse),
            exportPermission: 'Inventory Report Export',
        );
    }
}
