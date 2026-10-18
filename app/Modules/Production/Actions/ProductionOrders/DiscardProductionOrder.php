<?php

namespace App\Modules\Production\Actions\ProductionOrders;

use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Production\Enums\ProductionOrderStatus;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;

/**
 * Drop a draft order. A draft that was never started is deleted; one that
 * was started and stepped back has entries and stock movements in the
 * books, so it is kept as cancelled to explain them.
 */
class DiscardProductionOrder
{
    /**
     * @return bool Whether the order was deleted (else it was cancelled).
     */
    public function handle(ProductionOrder $order): bool
    {
        return DB::transaction(function () use ($order): bool {
            $order = ProductionOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== ProductionOrderStatus::Draft) {
                throw new AccountingException(__('Only draft production orders can be deleted. Reverse the order first.'));
            }

            if ($order->journalEntries()->exists() || $order->stockMovements()->exists()) {
                $order->update(['status' => ProductionOrderStatus::Cancelled]);

                return false;
            }

            $order->delete();

            return true;
        });
    }
}
