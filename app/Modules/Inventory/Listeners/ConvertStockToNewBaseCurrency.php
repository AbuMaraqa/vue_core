<?php

namespace App\Modules\Inventory\Listeners;

use App\Modules\Accounting\Events\BaseCurrencyChanged;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Inventory\Support\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Stock is valued in the base currency. Movements are converted one by one
 * and each stock level becomes the sum of its movements again, so stock keeps
 * matching what was posted to the inventory accounts.
 */
class ConvertStockToNewBaseCurrency
{
    public function handle(BaseCurrencyChanged $event): void
    {
        $conversion = $event->conversion;

        $conversion->convertColumns('items', ['sale_price' => 4, 'purchase_price' => 4]);
        $conversion->convertColumns('stock_movements', ['unit_cost' => StockLedger::COST_SCALE, 'value' => 4]);
        $conversion->convertColumns('stock_adjustment_lines', ['unit_cost' => StockLedger::COST_SCALE, 'value' => 4]);

        $values = [];

        foreach (DB::table('stock_movements')->select(['id', 'item_id', 'warehouse_id', 'value'])->lazyById() as $movement) {
            $key = "{$movement->item_id}-{$movement->warehouse_id}";
            $values[$key] = Decimal::of($values[$key] ?? null)->plus(Decimal::of((string) $movement->value));
        }

        DB::table('stock_levels')->orderBy('id')->each(function (object $level) use ($conversion, $values): void {
            $value = Decimal::round($values["{$level->item_id}-{$level->warehouse_id}"] ?? '0');

            DB::table('stock_levels')->where('id', $level->id)->update([
                'value' => $value,
                'average_cost' => Decimal::isPositive($level->quantity)
                    ? Decimal::divide($value, (string) $level->quantity, StockLedger::COST_SCALE)
                    : $conversion->amount((string) $level->average_cost, StockLedger::COST_SCALE),
            ]);
        });
    }
}
