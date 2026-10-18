<?php

namespace App\Modules\Invoicing\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * Where a quotation stands with the customer. Draft, sent, accepted and
 * rejected are stored; invoiced (it became a sales invoice) and expired
 * (still waiting after its validity date) are worked out by the model.
 */
enum QuotationStatus: string
{
    use EnumOptions;

    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Invoiced = 'invoiced';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Sent => __('Sent'),
            self::Accepted => __('Accepted'),
            self::Rejected => __('Rejected'),
            self::Expired => __('Expired'),
            self::Invoiced => __('Invoiced'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'light',
            self::Sent => 'info',
            self::Accepted => 'success',
            self::Rejected => 'error',
            self::Expired => 'warning',
            self::Invoiced => 'primary',
        };
    }

    /**
     * The statuses a user may move a quotation to from this one. A rejected
     * quotation can be offered again; an accepted one can still be refused.
     *
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent, self::Accepted, self::Rejected],
            self::Sent => [self::Accepted, self::Rejected],
            self::Accepted => [self::Rejected],
            self::Rejected => [self::Sent],
            self::Expired, self::Invoiced => [],
        };
    }

    /**
     * Only these are ever saved in the `status` column.
     *
     * @return list<self>
     */
    public static function stored(): array
    {
        return [self::Draft, self::Sent, self::Accepted, self::Rejected];
    }
}
