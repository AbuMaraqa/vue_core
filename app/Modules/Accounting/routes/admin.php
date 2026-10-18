<?php

use App\Modules\Accounting\Http\Controllers\AccountController;
use App\Modules\Accounting\Http\Controllers\AccountingSettingsController;
use App\Modules\Accounting\Http\Controllers\BudgetController;
use App\Modules\Accounting\Http\Controllers\CostCenterController;
use App\Modules\Accounting\Http\Controllers\CurrencyController;
use App\Modules\Accounting\Http\Controllers\FinancialReportController;
use App\Modules\Accounting\Http\Controllers\FiscalYearController;
use App\Modules\Accounting\Http\Controllers\JournalEntryController;
use App\Modules\Accounting\Http\Controllers\OpeningBalanceController;
use App\Modules\Accounting\Http\Controllers\TaxController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'can:Dashboard View'])
    ->prefix('{locale}/admin/accounting')
    ->name('admin.accounting.')
    ->group(function () {
        Route::resource('accounts', AccountController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::resource('journal-entries', JournalEntryController::class)->parameters(['journal-entries' => 'journalEntry']);
        Route::get('journal-entries/{journalEntry}/pdf', [JournalEntryController::class, 'pdf'])->name('journal-entries.pdf');
        Route::post('journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post'])->name('journal-entries.post');
        Route::post('journal-entries/{journalEntry}/reverse', [JournalEntryController::class, 'reverse'])->name('journal-entries.reverse');

        Route::get('opening-balances/create', [OpeningBalanceController::class, 'create'])->name('opening-balances.create');
        Route::post('opening-balances', [OpeningBalanceController::class, 'store'])->name('opening-balances.store');

        Route::resource('budgets', BudgetController::class)->except('show');

        Route::prefix('reports')->name('reports.')->controller(FinancialReportController::class)->group(function () {
            Route::get('overview', 'overview')->name('overview');
            Route::get('trial-balance', 'trialBalance')->name('trial-balance');
            Route::get('general-ledger', 'generalLedger')->name('general-ledger');
            Route::get('balance-sheet', 'balanceSheet')->name('balance-sheet');
            Route::get('income-statement', 'incomeStatement')->name('income-statement');
            Route::get('cash-flow', 'cashFlow')->name('cash-flow');
            Route::get('tax-summary', 'taxSummary')->name('tax-summary');
            Route::get('budget-vs-actual', 'budgetVsActual')->name('budget-vs-actual');
        });

        Route::resource('currencies', CurrencyController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('currencies/{currency}/rates', [CurrencyController::class, 'rates'])->name('currencies.rates.index');
        Route::post('currencies/{currency}/rates', [CurrencyController::class, 'storeRate'])->name('currencies.rates.store');
        Route::delete('currencies/{currency}/rates/{rate}', [CurrencyController::class, 'destroyRate'])->name('currencies.rates.destroy');
        Route::get('exchange-rate', [CurrencyController::class, 'lookup'])->name('exchange-rate');

        Route::resource('taxes', TaxController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('cost-centers', CostCenterController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['cost-centers' => 'costCenter']);

        Route::get('fiscal-years', [FiscalYearController::class, 'index'])->name('fiscal-years.index');
        Route::post('fiscal-years', [FiscalYearController::class, 'store'])->name('fiscal-years.store');
        Route::post('fiscal-years/{fiscalYear}/close', [FiscalYearController::class, 'close'])->name('fiscal-years.close');
        Route::post('fiscal-years/{fiscalYear}/reopen', [FiscalYearController::class, 'reopen'])->name('fiscal-years.reopen');

        Route::get('settings', [AccountingSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [AccountingSettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/base-currency', [AccountingSettingsController::class, 'changeBaseCurrency'])->name('settings.base-currency');
    });
