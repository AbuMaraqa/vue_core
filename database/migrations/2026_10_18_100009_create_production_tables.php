<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bills_of_materials', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4)->default(1);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bill_of_materials_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_of_materials_id')->constrained('bills_of_materials')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->decimal('waste_percent', 8, 4)->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('bill_of_materials_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_of_materials_id')->constrained('bills_of_materials')->cascadeOnDelete();
            $table->string('description');
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 20, 4);
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('date');
            $table->foreignId('bill_of_materials_id')->nullable()->constrained('bills_of_materials')->nullOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->decimal('produced_quantity', 20, 4)->nullable();
            $table->foreignId('materials_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('product_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference', 100)->nullable();
            $table->string('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->date('started_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->decimal('materials_cost', 20, 4)->default(0);
            $table->decimal('other_costs', 20, 4)->default(0);
            $table->decimal('total_cost', 20, 4)->default(0);
            $table->decimal('unit_cost', 20, 6)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'date']);
        });

        Schema::create('production_order_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_cost', 20, 6)->default(0);
            $table->decimal('issued_value', 20, 4)->default(0);
            $table->decimal('consumed_quantity', 20, 4)->nullable();
            $table->decimal('value', 20, 4)->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('production_order_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 20, 4);
            $table->unsignedSmallInteger('sort')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_order_costs');
        Schema::dropIfExists('production_order_materials');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('bill_of_materials_costs');
        Schema::dropIfExists('bill_of_materials_components');
        Schema::dropIfExists('bills_of_materials');
    }
};
