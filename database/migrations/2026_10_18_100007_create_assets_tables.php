<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fixed asset register: categories with their accounts, assets with
     * their depreciation method, and the monthly depreciation runs.
     */
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->foreignId('asset_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('accumulated_depreciation_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('depreciation_expense_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('method', 30)->default('straight_line');
            $table->unsignedSmallInteger('useful_life_months')->default(60);
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('name', 150);
            $table->foreignId('category_id')->constrained('asset_categories')->restrictOnDelete();
            $table->string('serial_number', 100)->nullable();
            $table->string('location', 150)->nullable();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('acquisition_date');
            $table->date('depreciation_start_date');
            $table->decimal('cost', 20, 4);
            $table->decimal('salvage_value', 20, 4)->default(0);
            $table->string('method', 30);
            $table->unsignedSmallInteger('useful_life_months');
            $table->decimal('declining_rate', 7, 4)->nullable();
            $table->decimal('opening_depreciation', 20, 4)->default(0);
            $table->decimal('accumulated_depreciation', 20, 4)->default(0);
            $table->date('depreciated_until')->nullable();
            $table->foreignId('funding_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_proceeds', 20, 4)->nullable();
            $table->foreignId('disposal_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'depreciation_start_date']);
        });

        Schema::create('depreciation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('period_end');
            $table->decimal('total', 20, 4)->default(0);
            $table->string('status', 20)->default('posted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depreciation_run_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->unsignedSmallInteger('months');
            $table->decimal('amount', 20, 4);
            $table->date('previous_depreciated_until')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_depreciations');
        Schema::dropIfExists('depreciation_runs');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
    }
};
