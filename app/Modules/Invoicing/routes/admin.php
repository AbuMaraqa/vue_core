<?php

use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Invoicing\Enums\PaymentType;
use App\Modules\Invoicing\Http\Controllers\InvoiceController;
use App\Modules\Invoicing\Http\Controllers\InvoicingReportController;
use App\Modules\Invoicing\Http\Controllers\PaymentController;
use App\Modules\Invoicing\Http\Controllers\QuotationController;
use Illuminate\Support\Facades\Route;

/*
 * Every document type has its own URLs and route names, all served by one
 * controller that reads the type from the route name:
 *
 *   /sales/invoices        admin.sales.invoices.*
 *   /sales/credit-notes    admin.sales.credit-notes.*
 *   /purchases/bills       admin.purchases.bills.*
 *   /purchases/debit-notes admin.purchases.debit-notes.*
 *   /sales/receipts        admin.sales.receipts.*
 *   /purchases/payments    admin.purchases.payments.*
 *
 * Quotations are not financial documents and have their own controller:
 *
 *   /sales/quotations      admin.sales.quotations.*
 */
Route::middleware(['web', 'auth', 'verified', 'can:Dashboard View'])
    ->prefix('{locale}/admin')
    ->name('admin.')
    ->group(function () {
        Route::prefix('sales/quotations')->name('sales.quotations.')->controller(QuotationController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{quotation}', 'show')->name('show');
            Route::get('{quotation}/edit', 'edit')->name('edit');
            Route::put('{quotation}', 'update')->name('update');
            Route::delete('{quotation}', 'destroy')->name('destroy');
            Route::post('{quotation}/status', 'status')->name('status');
            Route::post('{quotation}/convert', 'convert')->name('convert');
            Route::get('{quotation}/pdf', 'pdf')->name('pdf');
        });

        foreach (InvoiceType::cases() as $type) {
            $path = str_replace(['admin.', '.'], ['', '/'], $type->routeName());

            Route::prefix($path)->name(str_replace('admin.', '', $type->routeName()).'.')->controller(InvoiceController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('{invoice}', 'show')->name('show');
                Route::get('{invoice}/edit', 'edit')->name('edit');
                Route::put('{invoice}', 'update')->name('update');
                Route::delete('{invoice}', 'destroy')->name('destroy');
                Route::post('{invoice}/post', 'post')->name('post');
                Route::post('{invoice}/cancel', 'cancel')->name('cancel');
                Route::post('{invoice}/notes', 'applyNote')->name('apply-note');
                Route::delete('{invoice}/allocations/{allocation}', 'removeAllocation')->name('allocations.destroy');
                Route::get('{invoice}/pdf', 'pdf')->name('pdf');
            });
        }

        foreach (PaymentType::cases() as $type) {
            $path = str_replace(['admin.', '.'], ['', '/'], $type->routeName());

            Route::prefix($path)->name(str_replace('admin.', '', $type->routeName()).'.')->controller(PaymentController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('{payment}', 'show')->name('show');
                Route::post('{payment}/allocations', 'allocate')->name('allocate');
                Route::delete('{payment}/allocations/{allocation}', 'removeAllocation')->name('allocations.destroy');
                Route::post('{payment}/cancel', 'cancel')->name('cancel');
                Route::get('{payment}/pdf', 'pdf')->name('pdf');
            });
        }

        Route::prefix('invoicing/reports')->name('invoicing.reports.')->controller(InvoicingReportController::class)->group(function () {
            Route::get('aging', 'aging')->name('aging');
            Route::get('analysis', 'analysis')->name('analysis');
        });
    });
