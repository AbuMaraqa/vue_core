<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sales invoices, credit notes, purchase bills and debit notes share one
     * table; customer receipts and vendor payments another. Allocations say
     * which payment or credit note settled which invoice.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('number', 30)->nullable()->unique();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->date('due_date');
            $table->string('reference', 100)->nullable();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('exchange_rate', 20, 8)->default(1);
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('original_invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->boolean('prices_include_tax')->default(false);
            $table->boolean('is_opening')->default(false);
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('discount_total', 20, 4)->default(0);
            $table->decimal('tax_total', 20, 4)->default(0);
            $table->decimal('total', 20, 4)->default(0);
            $table->decimal('paid_amount', 20, 4)->default(0);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status', 'date']);
            $table->index(['contact_id', 'type', 'status']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 500)->nullable();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4)->default(1);
            $table->decimal('unit_price', 20, 6)->default(0);
            $table->decimal('discount_percent', 7, 4)->default(0);
            $table->foreignId('tax_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('discount_amount', 20, 4)->default(0);
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->decimal('total', 20, 4)->default(0);
            $table->decimal('unit_cost', 20, 6)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('number', 30)->unique();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('method', 20);
            $table->foreignId('bank_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cheque_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 20, 4);
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('exchange_rate', 20, 8)->default(1);
            $table->string('reference', 100)->nullable();
            $table->string('description')->nullable();
            $table->string('status', 20)->default('posted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'contact_id', 'status']);
        });

        Schema::create('allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('credit_note_id')->nullable()->constrained('invoices')->cascadeOnDelete();
            $table->decimal('amount', 20, 4);
            $table->date('date');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
