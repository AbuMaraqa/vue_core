<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employees, the pay components they receive or have deducted, and the
     * monthly payroll runs with one payslip per employee.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('national_id', 50)->nullable();
            $table->string('position', 150)->nullable();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->decimal('basic_salary', 20, 4)->default(0);
            $table->string('payment_method', 20)->default('bank');
            $table->string('bank_name', 150)->nullable();
            $table->string('iban', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pay_components', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('type', 30);
            $table->string('calculation', 20)->default('fixed');
            $table->decimal('default_value', 20, 4)->default(0);
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('liability_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_pay_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_component_id')->constrained()->restrictOnDelete();
            $table->decimal('value', 20, 4);
            $table->timestamps();

            $table->unique(['employee_id', 'pay_component_id']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->nullable()->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('payment_date')->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->decimal('gross_total', 20, 4)->default(0);
            $table->decimal('deductions_total', 20, 4)->default(0);
            $table->decimal('employer_total', 20, 4)->default(0);
            $table->decimal('net_total', 20, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('days_worked');
            $table->unsignedSmallInteger('days_in_period');
            $table->decimal('basic', 20, 4)->default(0);
            $table->decimal('earnings_total', 20, 4)->default(0);
            $table->decimal('deductions_total', 20, 4)->default(0);
            $table->decimal('employer_total', 20, 4)->default(0);
            $table->decimal('net_pay', 20, 4)->default(0);
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_component_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->decimal('amount', 20, 4);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_pay_components');
        Schema::dropIfExists('pay_components');
        Schema::dropIfExists('employees');
    }
};
