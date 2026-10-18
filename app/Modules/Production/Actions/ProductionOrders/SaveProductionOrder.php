<?php

namespace App\Modules\Production\Actions\ProductionOrders;

use App\Modules\Accounting\Enums\DocumentType;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Support\DocumentNumbers;
use App\Modules\Production\Data\ProductionOrderData;
use App\Modules\Production\Enums\ProductionOrderStatus;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Create or update a draft production order. A draft is only a plan: it
 * moves no stock and posts nothing. It is numbered when created, so the
 * workshop can refer to it from the first day.
 */
class SaveProductionOrder
{
    public function __construct(protected DocumentNumbers $numbers) {}

    public function handle(ProductionOrder $order, ProductionOrderData $data): ProductionOrder
    {
        return DB::transaction(function () use ($order, $data): ProductionOrder {
            if ($order->exists) {
                $order = ProductionOrder::query()->lockForUpdate()->findOrFail($order->id);

                if ($order->status !== ProductionOrderStatus::Draft) {
                    throw new AccountingException(__('Only draft production orders can be changed.'));
                }
            } else {
                $userId = Auth::id();

                $order->fill([
                    'number' => $this->numbers->next(DocumentType::ProductionOrder, $data->date),
                    'status' => ProductionOrderStatus::Draft,
                    'created_by' => is_int($userId) ? $userId : null,
                ]);
            }

            $order->fill([
                'date' => $data->date,
                'bill_of_materials_id' => $data->bill_of_materials_id,
                'item_id' => $data->item_id,
                'quantity' => $data->quantity,
                'materials_warehouse_id' => $data->materials_warehouse_id,
                'product_warehouse_id' => $data->product_warehouse_id,
                'contact_id' => $data->contact_id,
                'cost_center_id' => $data->cost_center_id,
                'reference' => $data->reference,
                'description' => $data->description,
            ])->save();

            $order->materials()->delete();
            $order->costs()->delete();

            foreach ($data->materials as $position => $material) {
                $order->materials()->create([
                    'item_id' => $material['item_id'],
                    'quantity' => $material['quantity'],
                    'sort' => $position,
                ]);
            }

            foreach ($data->costs as $position => $cost) {
                $order->costs()->create([
                    'description' => $cost['description'],
                    'account_id' => $cost['account_id'],
                    'amount' => $cost['amount'],
                    'sort' => $position,
                ]);
            }

            return $order;
        });
    }
}
