<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every financial effect in the system is a journal entry. Each line keeps
     * its amount in the document currency and in the base currency; reports
     * read the base amounts of posted entries only.
     */
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('closing_entry_id')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['type', 'year']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->date('date');
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('exchange_rate', 20, 8)->default(1);
            $table->decimal('total', 20, 4)->default(0);
            $table->decimal('base_total', 20, 4)->default(0);
            $table->nullableMorphs('source');
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'date']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->nullableMorphs('partner');
            $table->string('description')->nullable();
            $table->decimal('debit', 20, 4)->default(0);
            $table->decimal('credit', 20, 4)->default(0);
            $table->decimal('base_debit', 20, 4)->default(0);
            $table->decimal('base_credit', 20, 4)->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->unsignedBigInteger('reconciliation_id')->nullable()->index();
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 20, 4)->default(0);

            $table->unique(['budget_id', 'account_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('fiscal_years');
    }
};
