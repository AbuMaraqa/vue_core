<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The indexes the lists, reports and dashboard filter or sort by, keyed by
     * table.
     *
     * @var array<string, list<list<string>>>
     */
    protected array $indexes = [
        'journal_entries' => [['date', 'id']],
        'invoices' => [['type', 'date'], ['type', 'status', 'due_date']],
        'payments' => [['type', 'date']],
        'bank_transfers' => [['date']],
        'stock_adjustments' => [['date']],
        'stock_transfers' => [['date']],
        'production_orders' => [['date']],
        'activity_log' => [['created_at']],
        'asset_depreciations' => [['asset_id', 'to_date']],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->indexes as $tableName => $indexes) {
            Schema::table($tableName, function (Blueprint $table) use ($indexes): void {
                foreach ($indexes as $columns) {
                    $table->index($columns);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * MySQL may have dropped the implicit index of the asset_id foreign key
     * once (asset_id, to_date) covered it, so it gets its own index back first.
     */
    public function down(): void
    {
        if (! Schema::hasIndex('asset_depreciations', ['asset_id'])) {
            Schema::table('asset_depreciations', function (Blueprint $table): void {
                $table->index('asset_id');
            });
        }

        foreach ($this->indexes as $tableName => $indexes) {
            Schema::table($tableName, function (Blueprint $table) use ($indexes): void {
                foreach ($indexes as $columns) {
                    $table->dropIndex($columns);
                }
            });
        }
    }
};
