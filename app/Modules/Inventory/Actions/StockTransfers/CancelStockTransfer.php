<?php

namespace App\Modules\Inventory\Actions\StockTransfers;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Support\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Send transferred stock back: possible while the receiving warehouse still
 * holds it (or negative stock is allowed).
 */
class CancelStockTransfer
{
    public function __construct(
        protected StockLedger $stock,
        protected Ledger $ledger,
    ) {}

    public function handle(StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($transfer->status !== DocumentStatus::Posted) {
                throw new AccountingException(__('Only posted documents can be cancelled.'));
            }

            $this->stock->reverse($transfer, $this->ledger->openDateFor($transfer->date));

            $transfer->update(['status' => DocumentStatus::Cancelled]);

            return $transfer;
        });
    }
}
