<?php

namespace App\Modules\Invoicing\Providers;

use App\Models\User;
use App\Modules\Accounting\Events\BaseCurrencyChanged;
use App\Modules\Accounting\Support\DocumentLinks;
use App\Modules\Banking\Events\ChequeVoided;
use App\Modules\Core\Support\Dashboard\Dashboard;
use App\Modules\Core\Support\Dashboard\DashboardShortcut;
use App\Modules\Core\Support\Dashboard\DashboardStat;
use App\Modules\Core\Support\ModuleServiceProvider;
use App\Modules\Core\Support\Sidebar\Sidebar;
use App\Modules\Core\Support\Sidebar\SidebarGroup;
use App\Modules\Core\Support\Sidebar\SidebarItem;
use App\Modules\Foundation\Support\Calendar\Calendar;
use App\Modules\Invoicing\Calendar\PurchaseBillDueDates;
use App\Modules\Invoicing\Calendar\SalesInvoiceDueDates;
use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Invoicing\Enums\PaymentType;
use App\Modules\Invoicing\Listeners\ConvertInvoicingToNewBaseCurrency;
use App\Modules\Invoicing\Listeners\FreeInvoicesOfVoidedCheque;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Payment;
use App\Modules\Invoicing\Models\Quotation;
use App\Modules\Invoicing\Policies\InvoicePolicy;
use App\Modules\Invoicing\Policies\PaymentPolicy;
use App\Modules\Invoicing\Policies\QuotationPolicy;
use Illuminate\Support\Facades\Event;

/**
 * The Invoicing module: the sales and purchases cycles. Invoices post to the
 * Accounting ledger and move Inventory stock; payments go through Banking
 * accounts and cheques.
 */
class InvoicingServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Invoice::class => InvoicePolicy::class,
        Payment::class => PaymentPolicy::class,
        Quotation::class => QuotationPolicy::class,
    ];

    protected array $routes = [
        'routes/admin.php',
    ];

    public function boot(): void
    {
        parent::boot();

        $this->app->make(Calendar::class)->register(SalesInvoiceDueDates::class, PurchaseBillDueDates::class);

        $this->loadViewsFrom($this->modulePath('resources/views'), 'invoicing');

        Event::listen(ChequeVoided::class, FreeInvoicesOfVoidedCheque::class);
        Event::listen(BaseCurrencyChanged::class, ConvertInvoicingToNewBaseCurrency::class);

        $links = $this->app->make(DocumentLinks::class);
        $links->register(Invoice::class, fn (Invoice $invoice): array => [
            'label' => $invoice->type->label(),
            'url' => route("{$invoice->type->routeName()}.show", $invoice),
        ]);
        $links->register(Payment::class, fn (Payment $payment): array => [
            'label' => $payment->type->label(),
            'url' => route("{$payment->type->routeName()}.show", $payment),
        ]);
    }

    protected function modulePath(string $path = ''): string
    {
        return dirname(__DIR__).($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }

    protected function sidebar(Sidebar $sidebar): void
    {
        $sidebar->section('accounting', 'Accounting', sort: 30)->add(
            SidebarGroup::make('Sales')
                ->icon('cart')
                ->sort(6)
                ->add(
                    SidebarItem::make('Sales Quotations')
                        ->simpleTitle('Price quotes')
                        ->route('admin.sales.quotations.index')
                        ->activeWhen('admin.sales.quotations.*')
                        ->can('Sales Quotation View List'),
                    $this->documentItem(InvoiceType::SalesInvoice),
                    $this->documentItem(InvoiceType::SalesCreditNote)->simpleTitle('Sales returns'),
                    $this->paymentItem(PaymentType::Receipt, 'Customer Receipts')->simpleTitle('Money received from customers'),
                    SidebarItem::make('Aged Receivables')
                        ->simpleTitle('Who owes me')
                        ->route('admin.invoicing.reports.aging', ['side' => 'receivables'])
                        ->can('Invoicing Report View'),
                    SidebarItem::make('Sales Analysis')
                        ->route('admin.invoicing.reports.analysis', ['side' => 'sales'])
                        ->can('Invoicing Report View')
                        ->advanced(),
                ),
            SidebarGroup::make('Purchases')
                ->icon('bag')
                ->sort(7)
                ->add(
                    $this->documentItem(InvoiceType::PurchaseBill),
                    $this->documentItem(InvoiceType::PurchaseDebitNote)->simpleTitle('Purchase returns'),
                    $this->paymentItem(PaymentType::Payment, 'Vendor Payments')->simpleTitle('Money paid to vendors'),
                    SidebarItem::make('Aged Payables')
                        ->simpleTitle('What I owe vendors')
                        ->route('admin.invoicing.reports.aging', ['side' => 'payables'])
                        ->can('Invoicing Report View'),
                    SidebarItem::make('Purchases Analysis')
                        ->route('admin.invoicing.reports.analysis', ['side' => 'purchases'])
                        ->can('Invoicing Report View')
                        ->advanced(),
                ),
        );
    }

    protected function dashboard(Dashboard $dashboard): void
    {
        $dashboard->shortcuts(
            DashboardShortcut::make('New sale')
                ->description('An invoice to a customer, paid now or later.')
                ->icon('cart-plus')
                ->tone('success')
                ->route(InvoiceType::SalesInvoice->routeName().'.create')
                ->can(InvoiceType::SalesInvoice->permissionPrefix().' Create')
                ->sort(10),
            DashboardShortcut::make('New price quote')
                ->description('Prices you offer a customer; it becomes a sale once they agree.')
                ->icon('document-text')
                ->tone('info')
                ->route('admin.sales.quotations.create')
                ->can('Sales Quotation Create')
                ->sort(15),
            DashboardShortcut::make('New purchase')
                ->description('Goods or services you bought from a vendor.')
                ->icon('bag')
                ->tone('warning')
                ->route(InvoiceType::PurchaseBill->routeName().'.create')
                ->can(InvoiceType::PurchaseBill->permissionPrefix().' Create')
                ->sort(20),
            DashboardShortcut::make('Receive money from a customer')
                ->description('In cash, by bank transfer or by cheque.')
                ->icon('card-receive')
                ->tone('success')
                ->route(PaymentType::Receipt->routeName().'.create')
                ->can(PaymentType::Receipt->permissionPrefix().' Create')
                ->sort(30),
            DashboardShortcut::make('Pay a vendor')
                ->description('In cash, by bank transfer or by cheque.')
                ->icon('card-send')
                ->tone('error')
                ->route(PaymentType::Payment->routeName().'.create')
                ->can(PaymentType::Payment->permissionPrefix().' Create')
                ->sort(40),
        );

        $dashboard->stats(function (User $user): array {
            $type = InvoiceType::SalesInvoice;

            if (! $user->can("{$type->permissionPrefix()} View List")) {
                return [];
            }

            $overdue = Invoice::query()
                ->where('type', $type)
                ->open()
                ->where('due_date', '<', today())
                ->count();

            if ($overdue === 0) {
                return [];
            }

            return [
                DashboardStat::make('Overdue invoices')
                    ->value($overdue)
                    ->hint('Sales whose due date passed and are not fully paid.')
                    ->icon('danger')
                    ->tone('error')
                    ->route("{$type->routeName()}.index", ['filter' => ['payment' => 'overdue']])
                    ->sort(25),
            ];
        });
    }

    protected function documentItem(InvoiceType $type): SidebarItem
    {
        return SidebarItem::make(match ($type) {
            InvoiceType::SalesInvoice => 'Sales Invoices',
            InvoiceType::SalesCreditNote => 'Credit Notes',
            InvoiceType::PurchaseBill => 'Purchase Bills',
            InvoiceType::PurchaseDebitNote => 'Debit Notes',
        })
            ->route("{$type->routeName()}.index")
            ->activeWhen("{$type->routeName()}.*")
            ->can("{$type->permissionPrefix()} View List");
    }

    protected function paymentItem(PaymentType $type, string $title): SidebarItem
    {
        return SidebarItem::make($title)
            ->route("{$type->routeName()}.index")
            ->activeWhen("{$type->routeName()}.*")
            ->can("{$type->permissionPrefix()} View List");
    }
}
