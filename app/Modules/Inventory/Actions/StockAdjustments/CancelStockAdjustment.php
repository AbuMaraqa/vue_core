<?php

namespace App\Modules\Inventory\Actions\StockAdjustments;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Support\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Undo a posted adjustment: its stock movements and its journal entry are
 * reversed, on its own date while that period is open, else today.
 */
class CancelStockAdjustment
{
    public function __construct(
        protected StockLedger $stock,
        protected Ledger $ledger,
    ) {}

    public function handle(StockAdjustment $adjustment): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment): StockAdjustment {
            $adjustment = StockAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);

            if ($adjustment->status !== DocumentStatus::Posted) {
                throw new AccountingException(__('Only posted documents can be cancelled.'));
            }

            $date = $this->ledger->openDateFor($adjustment->date);

            $this->stock->reverse($adjustment, $date);
            $this->ledger->reverseEntriesOf($adjustment, $date, __('Cancellation of :number', ['number' => $adjustment->number]));

            $adjustment->update(['status' => DocumentStatus::Cancelled]);

            return $adjustment;
        });
    }
}
