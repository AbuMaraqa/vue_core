<?php

namespace App\Modules\Payroll\Providers;

use App\Modules\Accounting\Events\BaseCurrencyChanged;
use App\Modules\Accounting\Support\DocumentLinks;
use App\Modules\Core\Support\Dashboard\Dashboard;
use App\Modules\Core\Support\Dashboard\DashboardShortcut;
use App\Modules\Core\Support\ModuleServiceProvider;
use App\Modules\Core\Support\Sidebar\Sidebar;
use App\Modules\Core\Support\Sidebar\SidebarGroup;
use App\Modules\Core\Support\Sidebar\SidebarItem;
use App\Modules\Foundation\Support\Calendar\Calendar;
use App\Modules\Payroll\Calendar\EmployeeDates;
use App\Modules\Payroll\Calendar\PayrollDates;
use App\Modules\Payroll\Listeners\ConvertPayrollToNewBaseCurrency;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayComponent;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Policies\EmployeePolicy;
use App\Modules\Payroll\Policies\PayComponentPolicy;
use App\Modules\Payroll\Policies\PayrollRunPolicy;
use Illuminate\Support\Facades\Event;

/**
 * The Payroll module: employees, pay components and monthly payroll, accrued
 * and paid through the Accounting ledger and Banking accounts.
 */
class PayrollServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Employee::class => EmployeePolicy::class,
        PayComponent::class => PayComponentPolicy::class,
        PayrollRun::class => PayrollRunPolicy::class,
    ];

    protected array $routes = [
        'routes/admin.php',
    ];

    public function boot(): void
    {
        parent::boot();

        $this->app->make(Calendar::class)->register(PayrollDates::class, EmployeeDates::class);

        $this->loadViewsFrom($this->modulePath('resources/views'), 'payroll');

        Event::listen(BaseCurrencyChanged::class, ConvertPayrollToNewBaseCurrency::class);

        $this->app->make(DocumentLinks::class)->register(PayrollRun::class, fn (PayrollRun $run): array => [
            'label' => __('Payroll'),
            'url' => route('admin.payroll.runs.show', $run),
        ]);
    }

    protected function modulePath(string $path = ''): string
    {
        return dirname(__DIR__).($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }

    protected function sidebar(Sidebar $sidebar): void
    {
        $sidebar->section('accounting', 'Accounting', sort: 30)->add(
            SidebarGroup::make('Payroll')
                ->simpleTitle('Salaries')
                ->icon('user-id')
                ->sort(60)
                ->add(
                    SidebarItem::make('Payroll Runs')
                        ->simpleTitle('Monthly salaries')
                        ->route('admin.payroll.runs.index')
                        ->activeWhen('admin.payroll.runs.*')
                        ->can('Payroll Run View List'),
                    SidebarItem::make('Employees')
                        ->route('admin.payroll.employees.index')
                        ->activeWhen('admin.payroll.employees.*')
                        ->can('Employee View List'),
                    SidebarItem::make('Pay Components')
                        ->route('admin.payroll.components.index')
                        ->can('Pay Component View List')
                        ->advanced(),
                ),
        );
    }

    protected function dashboard(Dashboard $dashboard): void
    {
        $dashboard->shortcuts(
            DashboardShortcut::make('Monthly salaries')
                ->description('Prepare the salaries of the month, then pay them.')
                ->icon('hand-money')
                ->tone('brand')
                ->route('admin.payroll.runs.index')
                ->can('Payroll Run View List')
                ->sort(120),
        );
    }
}
