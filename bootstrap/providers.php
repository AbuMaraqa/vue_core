<?php

use App\Modules\Accounting\Providers\AccountingServiceProvider;
use App\Modules\Assets\Providers\AssetsServiceProvider;
use App\Modules\Banking\Providers\BankingServiceProvider;
use App\Modules\Contacts\Providers\ContactsServiceProvider;
use App\Modules\Core\Providers\CoreServiceProvider;
use App\Modules\Foundation\Providers\FoundationServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use App\Modules\Invoicing\Providers\InvoicingServiceProvider;
use App\Modules\Payroll\Providers\PayrollServiceProvider;
use App\Modules\Production\Providers\ProductionServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    CoreServiceProvider::class,
    FoundationServiceProvider::class,
    AccountingServiceProvider::class,
    ContactsServiceProvider::class,
    InventoryServiceProvider::class,
    BankingServiceProvider::class,
    InvoicingServiceProvider::class,
    AssetsServiceProvider::class,
    PayrollServiceProvider::class,
    ProductionServiceProvider::class,
];
