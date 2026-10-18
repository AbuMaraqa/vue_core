<?php

namespace App\Modules\Inventory\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * Why stock came in or went out.
 */
enum StockMovementType: string
{
    use EnumOptions;

    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Purchase = 'purchase';
    case PurchaseReturn = 'purchase_return';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case ProductionIssue = 'production_issue';
    case ProductionExtraIssue = 'production_extra_issue';
    case ProductionReturn = 'production_return';
    case ProductionOutput = 'production_output';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Adjustment => __('Adjustment'),
            self::TransferIn => __('Transfer in'),
            self::TransferOut => __('Transfer out'),
            self::Purchase => __('Purchase'),
            self::PurchaseReturn => __('Purchase return'),
            self::Sale => __('Sale'),
            self::SaleReturn => __('Sale return'),
            self::ProductionIssue => __('Issue to production'),
            self::ProductionExtraIssue => __('Extra issue to production'),
            self::ProductionReturn => __('Return from production'),
            self::ProductionOutput => __('Production output'),
            self::Reversal => __('Reversal'),
        };
    }
}
