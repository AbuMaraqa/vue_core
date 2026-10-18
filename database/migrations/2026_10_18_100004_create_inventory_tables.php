<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Items, warehouses and the stock ledger. Quantities have 4 decimals and
     * unit costs 6, so averages stay precise; values have 4 like all amounts.
     */
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('symbol', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->foreignId('inventory_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('cost_of_sales_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('sales_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('purchase_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->json('name');
            $table->string('address', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('barcode', 100)->nullable()->index();
            $table->json('name');
            $table->text('description')->nullable();
            $table->string('type', 20)->default('stock');
            $table->foreignId('category_id')->nullable()->constrained('item_categories')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('sale_price', 20, 4)->default(0);
            $table->decimal('purchase_price', 20, 4)->default(0);
            $table->foreignId('sales_tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->foreignId('purchase_tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->foreignId('sales_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('purchase_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('inventory_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('cost_of_sales_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->decimal('reorder_level', 20, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4)->default(0);
            $table->decimal('average_cost', 20, 6)->default(0);
            $table->decimal('value', 20, 4)->default(0);
            $table->timestamps();

            $table->unique(['item_id', 'warehouse_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('type', 30);
            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_cost', 20, 6);
            $table->decimal('value', 20, 4);
            $table->nullableMorphs('source');
            $table->string('description')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->boolean('is_reversed')->default(false);
            $table->timestamps();

            $table->index(['item_id', 'warehouse_id', 'date']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('date');
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->string('status', 20)->default('posted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_cost', 20, 6);
            $table->decimal('value', 20, 4);
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('date');
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('description')->nullable();
            $table->string('status', 20)->default('posted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->unsignedSmallInteger('sort')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_adjustment_lines');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('items');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('item_categories');
        Schema::dropIfExists('units');
    }
};
