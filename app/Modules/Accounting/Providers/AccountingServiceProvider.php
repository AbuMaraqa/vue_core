<?php

namespace App\Modules\Accounting\Providers;

use App\Models\User;
use App\Modules\Accounting\Calendar\FiscalYearDates;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Accounting\Policies\AccountPolicy;
use App\Modules\Accounting\Policies\BudgetPolicy;
use App\Modules\Accounting\Policies\CostCenterPolicy;
use App\Modules\Accounting\Policies\CurrencyPolicy;
use App\Modules\Accounting\Policies\JournalEntryPolicy;
use App\Modules\Accounting\Policies\TaxPolicy;
use App\Modules\Accounting\Printing\CompanyDetails;
use App\Modules\Accounting\Reports\FinancialOverview;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DocumentLinks;
use App\Modules\Core\Support\Dashboard\Dashboard;
use App\Modules\Core\Support\Dashboard\DashboardStat;
use App\Modules\Core\Support\ModuleServiceProvider;
use App\Modules\Core\Support\Sidebar\Sidebar;
use App\Modules\Core\Support\Sidebar\SidebarGroup;
use App\Modules\Core\Support\Sidebar\SidebarItem;
use App\Modules\Foundation\Printing\LetterheadFactory;
use App\Modules\Foundation\Support\Calendar\Calendar;

/**
 * The Accounting module: the general ledger every financial module posts to.
 * Chart of accounts, currencies, fiscal years, journal entries, taxes, cost
 * centers, budgets and the financial reports.
 */
class AccountingServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Account::class => AccountPolicy::class,
        JournalEntry::class => JournalEntryPolicy::class,
        Currency::class => CurrencyPolicy::class,
        Tax::class => TaxPolicy::class,
        CostCenter::class => CostCenterPolicy::class,
        Budget::class => BudgetPolicy::class,
    ];

    protected array $routes = [
        'routes/admin.php',
    ];

    public function register(): void
    {
        $this->app->singleton(DocumentLinks::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(Calendar::class)->register(FiscalYearDates::class);
        $this->app->make(LetterheadFactory::class)->register(CompanyDetails::class);

        $this->loadViewsFrom($this->modulePath('resources/views'), 'accounting');

        $this->app->make(DocumentLinks::class)->register(FiscalYear::class, fn (FiscalYear $year): array => [
            'label' => __('Year-end closing'),
            'url' => route('admin.accounting.fiscal-years.index'),
        ]);
    }

    protected function modulePath(string $path = ''): string
    {
        return dirname(__DIR__).($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }

    protected function sidebar(Sidebar $sidebar): void
    {
        $sidebar->section('accounting', 'Accounting', sort: 30)->simpleTitle('My daily work')->add(
            SidebarGroup::make('General Ledger')
                ->icon('book')
                ->advanced()
                ->sort(10)
                ->add(
                    SidebarItem::make('Chart of Accounts')
                        ->route('admin.accounting.accounts.index')
                        ->can('Account View List'),
                    SidebarItem::make('Journal Entries')
                        ->route('admin.accounting.journal-entries.index')
                        ->activeWhen('admin.accounting.journal-entries.*')
                        ->can('Journal Entry View List'),
                    SidebarItem::make('Cost Centers')
                        ->route('admin.accounting.cost-centers.index')
                        ->can('Cost Center View List'),
                    SidebarItem::make('Budgets')
                        ->route('admin.accounting.budgets.index')
                        ->activeWhen('admin.accounting.budgets.*')
                        ->can('Budget View List'),
                ),
            SidebarGroup::make('Financial Reports')
                ->simpleTitle('Reports')
                ->icon('chart')
                ->sort(80)
                ->add(
                    SidebarItem::make('Financial Overview')->simpleTitle('Financial summary')->route('admin.accounting.reports.overview')->can('Financial Report View'),
                    SidebarItem::make('Trial Balance')->route('admin.accounting.reports.trial-balance')->can('Financial Report View')->advanced(),
                    SidebarItem::make('General Ledger Report')->route('admin.accounting.reports.general-ledger')->can('Financial Report View')->advanced(),
                    SidebarItem::make('Balance Sheet')->route('admin.accounting.reports.balance-sheet')->can('Financial Report View')->advanced(),
                    SidebarItem::make('Income Statement')->simpleTitle('Profit and loss')->route('admin.accounting.reports.income-statement')->can('Financial Report View'),
                    SidebarItem::make('Cash Flow Statement')->route('admin.accounting.reports.cash-flow')->can('Financial Report View')->advanced(),
                    SidebarItem::make('Tax Report')->route('admin.accounting.reports.tax-summary')->can('Financial Report View')->advanced(),
                    SidebarItem::make('Budget vs Actual')->route('admin.accounting.reports.budget-vs-actual')->can('Financial Report View')->advanced(),
                ),
            SidebarGroup::make('Accounting Setup')
                ->icon('settings-minimalistic')
                ->advanced()
                ->sort(90)
                ->add(
                    SidebarItem::make('Currencies')
                        ->route('admin.accounting.currencies.index')
                        ->can('Currency View List'),
                    SidebarItem::make('Taxes')
                        ->route('admin.accounting.taxes.index')
                        ->can('Tax View List'),
                    SidebarItem::make('Fiscal Years')
                        ->route('admin.accounting.fiscal-years.index')
                        ->can('Fiscal Year View List'),
                    SidebarItem::make('Accounting Settings')
                        ->route('admin.accounting.settings.edit')
                        ->can('Accounting Setting View'),
                ),
        );
    }

    protected function dashboard(Dashboard $dashboard): void
    {
        $dashboard->stats(function (User $user): array {
            if (! $user->can('Financial Report View')) {
                return [];
            }

            try {
                $currency = app(Currencies::class)->base();
            } catch (AccountingException) {
                return [];
            }

            $figures = app(FinancialOverview::class)->summary();
            $money = fn (DashboardStat $stat, string $amount): DashboardStat => $stat->money($amount, $currency->code, $currency->decimal_places);
            $profit = Decimal::round(Decimal::of($figures['month_revenue'])->minus($figures['month_expenses']));

            $stats = [
                $money(DashboardStat::make('Money in cash and banks'), $figures['cash'])
                    ->hint('All the cash boxes and bank accounts together.')
                    ->icon('wallet')
                    ->tone('success')
                    ->route('admin.accounting.reports.overview')
                    ->sort(10),
                $money(DashboardStat::make('Customers owe you'), $figures['receivables'])
                    ->hint('Sales not paid yet.')
                    ->icon('card-receive')
                    ->tone('info')
                    ->route('admin.invoicing.reports.aging', ['side' => 'receivables'])
                    ->sort(20),
                $money(DashboardStat::make('You owe vendors'), $figures['payables'])
                    ->hint('Purchases and cheques you have not paid yet.')
                    ->icon('card-send')
                    ->tone('error')
                    ->route('admin.invoicing.reports.aging', ['side' => 'payables'])
                    ->sort(30),
                $money(DashboardStat::make('Profit this month'), $profit)
                    ->hint('Sales minus costs and expenses since the start of the month.')
                    ->icon('graph-up')
                    ->tone(Decimal::isNegative($profit) ? 'error' : 'success')
                    ->route('admin.accounting.reports.income-statement')
                    ->sort(40),
            ];

            if (Decimal::isPositive($figures['cheques_receivable'])) {
                $stats[] = $money(DashboardStat::make('Cheques not cashed yet'), $figures['cheques_receivable'])
                    ->hint('Cheques you received that are still in hand or at the bank.')
                    ->icon('bill-list')
                    ->tone('warning')
                    ->route('admin.banking.cheques.index')
                    ->sort(50);
            }

            return $stats;
        });
    }
}
