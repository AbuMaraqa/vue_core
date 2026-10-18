<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The chart of accounts is a tree. Group accounts only add up their
     * children; postings always go to non-group accounts.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->json('name');
            $table->string('type', 20)->index();
            $table->string('subtype', 40);
            $table->boolean('is_group')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('cost_centers')->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->json('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->decimal('rate', 8, 4);
            $table->string('scope', 20);
            $table->foreignId('sales_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('purchase_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('accounts');
    }
};
