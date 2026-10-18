<?php

namespace App\Modules\Banking\Providers;

use App\Models\User;
use App\Modules\Accounting\Events\BaseCurrencyChanged;
use App\Modules\Accounting\Support\DocumentLinks;
use App\Modules\Banking\Calendar\ChequeDueDates;
use App\Modules\Banking\Calendar\ChequeHandovers;
use App\Modules\Banking\Enums\ChequeDirection;
use App\Modules\Banking\Enums\VoucherDirection;
use App\Modules\Banking\Listeners\ConvertBankingToNewBaseCurrency;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\BankTransfer;
use App\Modules\Banking\Models\BankVoucher;
use App\Modules\Banking\Models\Cheque;
use App\Modules\Banking\Policies\BankAccountPolicy;
use App\Modules\Banking\Policies\BankTransferPolicy;
use App\Modules\Banking\Policies\BankVoucherPolicy;
use App\Modules\Banking\Policies\ChequePolicy;
use App\Modules\Core\Support\Dashboard\Dashboard;
use App\Modules\Core\Support\Dashboard\DashboardShortcut;
use App\Modules\Core\Support\Dashboard\DashboardStat;
use App\Modules\Core\Support\ModuleServiceProvider;
use App\Modules\Core\Support\Sidebar\Sidebar;
use App\Modules\Core\Support\Sidebar\SidebarGroup;
use App\Modules\Core\Support\Sidebar\SidebarItem;
use App\Modules\Foundation\Support\Calendar\Calendar;
use Illuminate\Support\Facades\Event;

/**
 * The Banking module: cash boxes and bank accounts, transfers, payment and
 * receipt vouchers, bank reconciliation and the cheque register.
 */
class BankingServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        BankAccount::class => BankAccountPolicy::class,
        BankTransfer::class => BankTransferPolicy::class,
        BankVoucher::class => BankVoucherPolicy::class,
        Cheque::class => ChequePolicy::class,
    ];

    protected array $routes = [
        'routes/admin.php',
    ];

    public function boot(): void
    {
        parent::boot();

        $this->app->make(Calendar::class)->register(ChequeDueDates::class, ChequeHandovers::class);

        $this->loadViewsFrom($this->modulePath('resources/views'), 'banking');

        Event::listen(BaseCurrencyChanged::class, ConvertBankingToNewBaseCurrency::class);

        $links = $this->app->make(DocumentLinks::class);
        $links->register(BankTransfer::class, fn (BankTransfer $transfer): array => [
            'label' => __('Transfer'),
            'url' => route('admin.banking.transfers.show', $transfer),
        ]);
        $links->register(BankVoucher::class, fn (BankVoucher $voucher): array => [
            'label' => $voucher->direction->label(),
            'url' => route('admin.banking.vouchers.show', $voucher),
        ]);
        $links->register(Cheque::class, fn (Cheque $cheque): array => [
            'label' => $cheque->direction->label(),
            'url' => route('admin.banking.cheques.show', $cheque),
        ]);
    }

    protected function modulePath(string $path = ''): string
    {
        return dirname(__DIR__).($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }

    protected function sidebar(Sidebar $sidebar): void
    {
        $sidebar->section('accounting', 'Accounting', sort: 30)->add(
            SidebarGroup::make('Banking')
                ->simpleTitle('Cash and banks')
                ->icon('banknote')
                ->sort(30)
                ->add(
                    SidebarItem::make('Bank & Cash Accounts')
                        ->simpleTitle('Cash boxes and bank accounts')
                        ->route('admin.banking.accounts.index')
                        ->activeWhen('admin.banking.accounts.*')
                        ->can('Bank Account View List'),
                    SidebarItem::make('Cheques')
                        ->route('admin.banking.cheques.index')
                        ->activeWhen('admin.banking.cheques.*')
                        ->can('Cheque View List'),
                    SidebarItem::make('Record an expense')
                        ->route('admin.banking.vouchers.create', ['direction' => VoucherDirection::Out->value, 'kind' => 'expense'])
                        ->can('Bank Voucher Create'),
                    SidebarItem::make('Vouchers')
                        ->simpleTitle('Expenses and other payments')
                        ->route('admin.banking.vouchers.index')
                        ->activeWhen('admin.banking.vouchers.*')
                        ->can('Bank Voucher View List'),
                    SidebarItem::make('Transfers')
                        ->simpleTitle('Move money between accounts')
                        ->route('admin.banking.transfers.index')
                        ->activeWhen('admin.banking.transfers.*')
                        ->can('Bank Transfer View List'),
                ),
        );
    }

    protected function dashboard(Dashboard $dashboard): void
    {
        $dashboard->shortcuts(
            DashboardShortcut::make('Record an expense')
                ->description('Rent, electricity, transport and other running costs.')
                ->icon('cash-out')
                ->tone('error')
                ->route('admin.banking.vouchers.create', ['direction' => VoucherDirection::Out->value, 'kind' => 'expense'])
                ->can('Bank Voucher Create')
                ->sort(50),
            DashboardShortcut::make('Receive a cheque')
                ->description('A cheque a customer gave you.')
                ->icon('bill-list')
                ->tone('success')
                ->route('admin.banking.cheques.create', ['direction' => ChequeDirection::Incoming->value])
                ->can('Cheque Create')
                ->sort(60),
            DashboardShortcut::make('Write a cheque')
                ->description('A cheque you gave to a vendor or someone else.')
                ->icon('bill-list')
                ->tone('warning')
                ->route('admin.banking.cheques.create', ['direction' => ChequeDirection::Outgoing->value])
                ->can('Cheque Create')
                ->sort(65),
        );

        $dashboard->stats(function (User $user): array {
            if (! $user->can('Cheque View List')) {
                return [];
            }

            $dueSoon = Cheque::query()
                ->open()
                ->where('due_date', '<', now()->addDays(8)->startOfDay())
                ->count();

            if ($dueSoon === 0) {
                return [];
            }

            return [
                DashboardStat::make('Cheques due this week')
                    ->value($dueSoon)
                    ->hint('Cheques to cash or to cover in the next 7 days, or already late.')
                    ->icon('calendar')
                    ->tone('warning')
                    ->route('admin.banking.cheques.index')
                    ->sort(60),
            ];
        });
    }
}
