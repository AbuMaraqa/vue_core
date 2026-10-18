<?php

namespace App\Modules\Invoicing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Controllers\Concerns\RendersReports;
use App\Modules\Foundation\Exports\Report;
use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Invoicing\Reports\AgedBalances;
use App\Modules\Invoicing\Reports\SalesAnalysis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sales and purchases reports: aged receivables and payables, and sales or
 * purchases by contact or item.
 */
class InvoicingReportController extends Controller
{
    use RendersReports;

    public function aging(Request $request, AgedBalances $report): Response
    {
        Gate::authorize('Invoicing Report View');

        $type = $request->input('side') === 'payables' ? InvoiceType::PurchaseBill : InvoiceType::SalesInvoice;
        $asOf = $request->date('as_of') ?? now();

        return $this->report(
            $request,
            'admin/invoicing/reports/Aging',
            [
                'report' => $report->generate($type, $asOf),
                'filters' => ['side' => $type->isSales() ? 'receivables' : 'payables', 'as_of' => $asOf->toDateString()],
            ],
            fn (): Report => $report->export($type, $asOf),
            exportPermission: 'Invoicing Report Export',
        );
    }

    public function analysis(Request $request, SalesAnalysis $report): Response
    {
        Gate::authorize('Invoicing Report View');

        [$from, $to] = $this->reportPeriod($request);
        $sales = $request->input('side') !== 'purchases';
        $groupBy = $request->input('group_by') === 'item' ? 'item' : 'contact';

        return $this->report(
            $request,
            'admin/invoicing/reports/Analysis',
            [
                'report' => $report->generate($sales, $groupBy, $from, $to),
                'filters' => ['side' => $sales ? 'sales' : 'purchases', 'group_by' => $groupBy, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            ],
            fn (): Report => $report->export($sales, $groupBy, $from, $to),
            exportPermission: 'Invoicing Report Export',
        );
    }
}
