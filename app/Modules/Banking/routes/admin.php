<?php

use App\Modules\Banking\Http\Controllers\BankAccountController;
use App\Modules\Banking\Http\Controllers\BankTransferController;
use App\Modules\Banking\Http\Controllers\BankVoucherController;
use App\Modules\Banking\Http\Controllers\ChequeBookController;
use App\Modules\Banking\Http\Controllers\ChequeController;
use App\Modules\Banking\Http\Controllers\ReconciliationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'can:Dashboard View'])
    ->prefix('{locale}/admin/banking')
    ->name('admin.banking.')
    ->group(function () {
        Route::resource('accounts', BankAccountController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->parameters(['accounts' => 'bankAccount']);
        Route::get('accounts/{bankAccount}/pdf', [BankAccountController::class, 'pdf'])->name('accounts.pdf');
        Route::post('accounts/{bankAccount}/cheque-books', [ChequeBookController::class, 'store'])->name('accounts.cheque-books.store');
        Route::put('accounts/{bankAccount}/cheque-books/{chequeBook}', [ChequeBookController::class, 'update'])->name('accounts.cheque-books.update');
        Route::delete('accounts/{bankAccount}/cheque-books/{chequeBook}', [ChequeBookController::class, 'destroy'])->name('accounts.cheque-books.destroy');
        Route::get('accounts/{bankAccount}/reconcile', [ReconciliationController::class, 'create'])->name('accounts.reconcile');
        Route::post('accounts/{bankAccount}/reconcile', [ReconciliationController::class, 'store'])->name('accounts.reconcile.store');
        Route::delete('accounts/{bankAccount}/reconciliations/{reconciliation}', [ReconciliationController::class, 'destroy'])->name('accounts.reconciliations.destroy');

        Route::resource('transfers', BankTransferController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('transfers/{transfer}/pdf', [BankTransferController::class, 'pdf'])->name('transfers.pdf');
        Route::post('transfers/{transfer}/cancel', [BankTransferController::class, 'cancel'])->name('transfers.cancel');

        Route::resource('vouchers', BankVoucherController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('vouchers/{voucher}/pdf', [BankVoucherController::class, 'pdf'])->name('vouchers.pdf');
        Route::post('vouchers/{voucher}/cancel', [BankVoucherController::class, 'cancel'])->name('vouchers.cancel');

        Route::get('cheques/export', [ChequeController::class, 'export'])->can('Cheque Export')->name('cheques.export');
        Route::resource('cheques', ChequeController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('cheques/{cheque}/pdf', [ChequeController::class, 'pdf'])->name('cheques.pdf');
        Route::post('cheques/{cheque}/actions', [ChequeController::class, 'perform'])->name('cheques.perform');
        Route::post('cheques/{cheque}/undo', [ChequeController::class, 'undo'])->name('cheques.undo');
    });
