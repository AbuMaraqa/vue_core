<?php

namespace App\Modules\Invoicing\Calendar;

use App\Models\User;
use App\Modules\Foundation\Support\Calendar\CalendarEntry;
use App\Modules\Foundation\Support\Calendar\CalendarSource;
use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Invoicing\Models\Invoice;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * The due dates of invoices that are not fully paid yet, with what is still
 * owed; overdue ones turn red. Paid invoices leave the calendar.
 */
abstract class InvoiceDueDates extends CalendarSource
{
    abstract protected function invoiceType(): InvoiceType;

    public function permission(): string
    {
        return "{$this->invoiceType()->permissionPrefix()} View List";
    }

    public function entries(CarbonInterface $from, CarbonInterface $to, User $user): iterable
    {
        $today = now()->startOfDay();

        return $this->openInvoices()
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->map(fn (Invoice $invoice): CalendarEntry => $this->entry($invoice, $invoice->due_date->lessThan($today)));
    }

    public function overdue(CarbonInterface $today, User $user): iterable
    {
        return $this->openInvoices()
            ->whereDate('due_date', '<', $today->toDateString())
            ->orderBy('due_date')
            ->limit(100)
            ->get()
            ->map(fn (Invoice $invoice): CalendarEntry => $this->entry($invoice, true));
    }

    /**
     * @return Builder<Invoice>
     */
    protected function openInvoices(): Builder
    {
        return Invoice::query()
            ->with(['contact', 'currency'])
            ->where('type', $this->invoiceType())
            ->open();
    }

    protected function entry(Invoice $invoice, bool $overdue): CalendarEntry
    {
        return new CalendarEntry(
            id: "invoice-due-{$invoice->id}",
            type: $this->key(),
            date: $invoice->due_date,
            title: __(':type :number due', ['type' => $invoice->type->label(), 'number' => $invoice->label()]),
            subtitle: $invoice->contact->name,
            url: route("{$invoice->type->routeName()}.show", $invoice),
            status: $invoice->paymentStatus()->label(),
            amount: $invoice->amountDue(),
            currency: $invoice->currency->code,
            decimals: $invoice->currency->decimal_places,
            color: $overdue ? 'error' : null,
            overdue: $overdue,
        );
    }
}
