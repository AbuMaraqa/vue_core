<?php

namespace App\Modules\Contacts\Providers;

use App\Modules\Accounting\Events\BaseCurrencyChanged;
use App\Modules\Contacts\Listeners\ConvertContactsToNewBaseCurrency;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Policies\ContactPolicy;
use App\Modules\Core\Support\Dashboard\Dashboard;
use App\Modules\Core\Support\Dashboard\DashboardShortcut;
use App\Modules\Core\Support\ModuleServiceProvider;
use App\Modules\Core\Support\Sidebar\Sidebar;
use App\Modules\Core\Support\Sidebar\SidebarItem;
use Illuminate\Support\Facades\Event;

/**
 * The Contacts module: customers and vendors, with their balances and
 * statements of account. It builds on the Accounting module's ledger.
 */
class ContactsServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Contact::class => ContactPolicy::class,
    ];

    protected array $routes = [
        'routes/admin.php',
    ];

    public function boot(): void
    {
        parent::boot();

        $this->loadViewsFrom($this->modulePath('resources/views'), 'contacts');

        Event::listen(BaseCurrencyChanged::class, ConvertContactsToNewBaseCurrency::class);
    }

    protected function modulePath(string $path = ''): string
    {
        return dirname(__DIR__).($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }

    protected function sidebar(Sidebar $sidebar): void
    {
        $sidebar->section('accounting', 'Accounting', sort: 30)->add(
            SidebarItem::make('Customers & Vendors')
                ->icon('contacts')
                ->route('admin.contacts.index')
                ->activeWhen('admin.contacts.*')
                ->can('Contact View List')
                ->sort(5),
        );
    }

    protected function dashboard(Dashboard $dashboard): void
    {
        $dashboard->shortcuts(
            DashboardShortcut::make('Add a customer or vendor')
                ->description('Someone you sell to or buy from.')
                ->icon('user-plus')
                ->tone('brand')
                ->route('admin.contacts.create')
                ->can('Contact Create')
                ->sort(90),
            DashboardShortcut::make('Customer statement')
                ->description('Everything a customer bought and paid, and what they still owe.')
                ->icon('document-text')
                ->tone('info')
                ->route('admin.contacts.index', ['filter' => ['type' => 'customers']])
                ->can('Contact View List')
                ->sort(100),
            DashboardShortcut::make('Vendor statement')
                ->description('Everything you bought from a vendor and paid, and what you still owe.')
                ->icon('document-text')
                ->tone('warning')
                ->route('admin.contacts.index', ['filter' => ['type' => 'vendors']])
                ->can('Contact View List')
                ->sort(110),
        );
    }
}
