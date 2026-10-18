<?php

namespace App\Modules\Banking\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * Where a cheque is in its life.
 *
 * Received: in hand → under collection (deposited) → collected, or bounced
 * (and re-presented or returned), endorsed to a vendor, or returned to the
 * customer. Issued: issued → cleared by the bank, or bounced (and reissued
 * or cancelled), or cancelled.
 */
enum ChequeStatus: string
{
    use EnumOptions;

    case InHand = 'in_hand';
    case UnderCollection = 'under_collection';
    case Collected = 'collected';
    case Endorsed = 'endorsed';
    case Returned = 'returned';
    case Issued = 'issued';
    case Cleared = 'cleared';
    case Cancelled = 'cancelled';
    case Bounced = 'bounced';

    public function label(): string
    {
        return match ($this) {
            self::InHand => __('In hand'),
            self::UnderCollection => __('Under collection'),
            self::Collected => __('Collected'),
            self::Endorsed => __('Endorsed'),
            self::Returned => __('Returned'),
            self::Issued => __('Issued'),
            self::Cleared => __('Cleared'),
            self::Cancelled => __('Cancelled'),
            self::Bounced => __('Bounced'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InHand, self::Issued => 'info',
            self::UnderCollection => 'warning',
            self::Collected, self::Cleared => 'success',
            self::Endorsed => 'primary',
            self::Returned, self::Cancelled => 'light',
            self::Bounced => 'error',
        };
    }

    /**
     * Whether the cheque still waits for the bank: in hand, deposited or
     * issued. These are the cheques of the "cheques due" report.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::InHand, self::UnderCollection, self::Issued], true);
    }

    /**
     * Whether the cheque no longer settles anything: its amount is back on
     * the contact.
     */
    public function isVoid(): bool
    {
        return in_array($this, [self::Bounced, self::Returned, self::Cancelled], true);
    }

    /**
     * The statuses of cheques that still wait for the bank.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isOpen()));
    }

    /**
     * @return list<self>
     */
    public static function forDirection(ChequeDirection $direction): array
    {
        return $direction === ChequeDirection::Incoming
            ? [self::InHand, self::UnderCollection, self::Collected, self::Bounced, self::Endorsed, self::Returned]
            : [self::Issued, self::Cleared, self::Bounced, self::Cancelled];
    }
}
