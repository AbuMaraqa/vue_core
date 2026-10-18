<?php

namespace App\Modules\Assets\Listeners;

use App\Modules\Accounting\Events\BaseCurrencyChanged;

/**
 * Asset costs and depreciation are kept in the base currency.
 */
class ConvertAssetsToNewBaseCurrency
{
    public function handle(BaseCurrencyChanged $event): void
    {
        $conversion = $event->conversion;

        $conversion->convertColumns('assets', [
            'cost' => 4,
            'salvage_value' => 4,
            'opening_depreciation' => 4,
            'accumulated_depreciation' => 4,
            'disposal_proceeds' => 4,
        ]);
        $conversion->convertColumns('depreciation_runs', ['total' => 4]);
        $conversion->convertColumns('asset_depreciations', ['amount' => 4]);
    }
}
