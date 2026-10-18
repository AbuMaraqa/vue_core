<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customers and vendors share one table: a company that buys and sells to
     * us is one contact with one statement.
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('kind', 20)->default('company');
            $table->string('name', 150)->index();
            $table->string('contact_person', 150)->nullable();
            $table->boolean('is_customer')->default(false)->index();
            $table->boolean('is_vendor')->default(false)->index();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('receivable_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('payable_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->decimal('credit_limit', 20, 4)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
