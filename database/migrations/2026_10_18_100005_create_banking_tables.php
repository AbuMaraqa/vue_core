<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cash boxes and bank accounts, the money that moves between them, bank
     * reconciliations and cheques. Every financial effect is a journal entry;
     * these tables keep the documents and the cheque lifecycle.
     */
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('type', 10)->default('bank');
            $table->foreignId('account_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('bank_name', 150)->nullable();
            $table->string('branch', 150)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('iban', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bank_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('date');
            $table->foreignId('from_bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('to_bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->decimal('amount', 20, 4);
            $table->decimal('to_amount', 20, 4);
            $table->decimal('fees', 20, 4)->default(0);
            $table->string('reference', 100)->nullable();
            $table->string('description')->nullable();
            $table->string('status', 20)->default('posted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('direction', 10);
            $table->date('date');
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('payee', 150)->nullable();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('exchange_rate', 20, 8)->default(1);
            $table->string('reference', 100)->nullable();
            $table->string('description')->nullable();
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('tax_total', 20, 4)->default(0);
            $table->decimal('total', 20, 4)->default(0);
            $table->string('status', 20)->default('posted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['direction', 'date']);
        });

        Schema::create('bank_voucher_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('tax_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->decimal('amount', 20, 4);
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->date('statement_date');
            $table->decimal('statement_balance', 20, 4);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cheque_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('first_number');
            $table->unsignedBigInteger('last_number');
            $table->unsignedBigInteger('next_number');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 10);
            $table->string('number', 30);
            $table->string('status', 30);
            $table->foreignId('contact_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cheque_book_id')->nullable()->constrained()->nullOnDelete();
            $table->string('drawee_bank', 150)->nullable();
            $table->decimal('amount', 20, 4);
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('exchange_rate', 20, 8)->default(1);
            $table->date('issue_date');
            $table->date('due_date');
            $table->foreignId('counter_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('endorsed_to_contact_id')->nullable()->constrained('contacts')->restrictOnDelete();
            $table->nullableMorphs('source');
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['direction', 'status', 'due_date']);
        });

        Schema::create('cheque_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cheque_id')->constrained()->cascadeOnDelete();
            $table->string('action', 30);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->date('date');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->boolean('is_undone')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cheque_events');
        Schema::dropIfExists('cheques');
        Schema::dropIfExists('cheque_books');
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_voucher_lines');
        Schema::dropIfExists('bank_vouchers');
        Schema::dropIfExists('bank_transfers');
        Schema::dropIfExists('bank_accounts');
    }
};
