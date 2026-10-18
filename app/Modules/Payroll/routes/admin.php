<?php

use App\Modules\Payroll\Http\Controllers\EmployeeController;
use App\Modules\Payroll\Http\Controllers\PayComponentController;
use App\Modules\Payroll\Http\Controllers\PayrollRunController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'can:Dashboard View'])
    ->prefix('{locale}/admin/payroll')
    ->name('admin.payroll.')
    ->group(function () {
        Route::resource('employees', EmployeeController::class);
        Route::resource('components', PayComponentController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::resource('runs', PayrollRunController::class)->only(['index', 'store', 'show']);
        Route::get('runs/{run}/pdf', [PayrollRunController::class, 'pdf'])->name('runs.pdf');
        Route::get('runs/{run}/payslips/{payslip}/pdf', [PayrollRunController::class, 'payslipPdf'])->name('runs.payslips.pdf');
        Route::post('runs/{run}/prepare', [PayrollRunController::class, 'prepare'])->name('runs.prepare');
        Route::put('runs/{run}/payslips/{payslip}', [PayrollRunController::class, 'updatePayslip'])->name('runs.payslips.update');
        Route::post('runs/{run}/post', [PayrollRunController::class, 'post'])->name('runs.post');
        Route::post('runs/{run}/pay', [PayrollRunController::class, 'pay'])->name('runs.pay');
        Route::post('runs/{run}/post-and-pay', [PayrollRunController::class, 'postAndPay'])->name('runs.post-and-pay');
        Route::post('runs/{run}/reverse', [PayrollRunController::class, 'reverse'])->name('runs.reverse');
    });
