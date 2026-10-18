<?php

namespace App\Modules\Production\Listeners;

use App\Modules\Accounting\Events\BaseCurrencyChanged;
use App\Modules\Inventory\Support\StockLedger;

/**
 * Recipes' other costs and production order costs are base amounts.
 */
class ConvertProductionToNewBaseCurrency
{
    public function handle(BaseCurrencyChanged $event): void
    {
        $conversion = $event->conversion;

        $conversion->convertColumns('bill_of_materials_costs', ['amount' => 4]);
        $conversion->convertColumns('production_orders', [
            'materials_cost' => 4,
            'other_costs' => 4,
            'total_cost' => 4,
            'unit_cost' => StockLedger::COST_SCALE,
        ]);
        $conversion->convertColumns('production_order_materials', [
            'unit_cost' => StockLedger::COST_SCALE,
            'issued_value' => 4,
            'value' => 4,
        ]);
        $conversion->convertColumns('production_order_costs', ['amount' => 4]);
    }
}
