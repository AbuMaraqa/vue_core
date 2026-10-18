<?php

namespace App\Modules\Inventory\Printing;

use App\Modules\Accounting\Printing\FinancialDocument;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Inventory\Models\StockTransfer;

/**
 * The printed stock transfer: the items moved from one warehouse to another,
 * signed by the store keeper who issued them, the one who delivered them and
 * the one who received them.
 */
class StockTransferDocument extends FinancialDocument
{
    public function __construct(protected StockTransfer $transfer)
    {
        $transfer->loadMissing(['fromWarehouse', 'toWarehouse', 'creator', 'lines.item.unit']);
    }

    public function title(): string
    {
        return __('Stock transfer');
    }

    public function number(): ?string
    {
        return $this->transfer->number;
    }

    public function status(): ?array
    {
        return $this->documentStatus($this->transfer->status);
    }

    public function details(): array
    {
        return [
            __('Date') => self::date($this->transfer->date),
            __('Created by') => $this->transfer->creator?->name,
            __('From warehouse') => $this->transfer->fromWarehouse->label(),
            __('To warehouse') => $this->transfer->toWarehouse->label(),
        ];
    }

    public function signatures(): array
    {
        return [__('Issued by'), __('Delivered by'), __('Received by')];
    }

    protected function view(): string
    {
        return 'inventory::print.stock-transfer';
    }

    protected function data(): array
    {
        return [
            'transfer' => $this->transfer,
            'totalQuantity' => Decimal::round(Decimal::sum($this->transfer->lines->pluck('quantity'))),
        ];
    }
}
