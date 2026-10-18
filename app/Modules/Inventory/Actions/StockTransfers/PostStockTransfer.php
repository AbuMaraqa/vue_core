<?php

namespace App\Modules\Inventory\Actions\StockTransfers;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Enums\DocumentType;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DocumentNumbers;
use App\Modules\Inventory\Data\StockTransferData;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Support\StockLedger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Move stock between warehouses. Each item leaves at the sending
 * warehouse's average cost and arrives with exactly that value, so the total
 * stock value does not change and nothing is posted to the books.
 */
class PostStockTransfer
{
    public function __construct(
        protected StockLedger $stock,
        protected Ledger $ledger,
        protected DocumentNumbers $numbers,
    ) {}

    public function handle(StockTransferData $data): StockTransfer
    {
        return DB::transaction(function () use ($data): StockTransfer {
            $this->ledger->ensureDateIsOpen($data->date);

            $userId = Auth::id();
            $transfer = StockTransfer::query()->create([
                'number' => $this->numbers->next(DocumentType::StockTransfer, $data->date),
                'date' => $data->date,
                'from_warehouse_id' => $data->from_warehouse_id,
                'to_warehouse_id' => $data->to_warehouse_id,
                'description' => $data->description,
                'status' => DocumentStatus::Posted,
                'created_by' => is_int($userId) ? $userId : null,
            ]);

            $items = Item::query()->findMany(array_column($data->lines, 'item_id'))->keyBy('id');

            foreach ($data->lines as $position => $line) {
                $item = $items[$line['item_id']];

                $out = $this->stock->issue($item, $data->from_warehouse_id, $line['quantity'], $data->date, StockMovementType::TransferOut, $transfer, $data->description);

                $this->stock->receive(
                    $item,
                    $data->to_warehouse_id,
                    $line['quantity'],
                    $out->unit_cost,
                    $data->date,
                    StockMovementType::TransferIn,
                    $transfer,
                    $data->description,
                    value: Decimal::of($out->value)->negated(),
                );

                $transfer->lines()->create(['item_id' => $item->id, 'quantity' => Decimal::round($line['quantity']), 'sort' => $position]);
            }

            return $transfer;
        });
    }
}
