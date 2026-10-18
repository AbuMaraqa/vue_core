<?php

namespace App\Modules\Banking\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * What can be done to a cheque, and the status it moves to from each status
 * it is allowed from. `Receive` and `Issue` create a cheque.
 */
enum ChequeAction: string
{
    use EnumOptions;

    case Receive = 'receive';
    case Issue = 'issue';
    case Deposit = 'deposit';
    case Collect = 'collect';
    case Bounce = 'bounce';
    case Represent = 'represent';
    case Endorse = 'endorse';
    case Return = 'return';
    case Clear = 'clear';
    case Cancel = 'cancel';
    case Reissue = 'reissue';

    public function label(): string
    {
        return match ($this) {
            self::Receive => __('Receive'),
            self::Issue => __('Issue'),
            self::Deposit => __('Deposit in bank'),
            self::Collect => __('Collect'),
            self::Bounce => __('Mark as bounced'),
            self::Represent => __('Re-present'),
            self::Endorse => __('Endorse to vendor'),
            self::Return => __('Return to customer'),
            self::Clear => __('Mark as cleared'),
            self::Cancel => __('Cancel'),
            self::Reissue => __('Reissue'),
        };
    }

    /**
     * The status the action moves a cheque to, keyed by the status it moves
     * from. Empty when the action does not apply to the direction.
     *
     * @return array<string, ChequeStatus>
     */
    public function transitions(ChequeDirection $direction): array
    {
        if ($direction === ChequeDirection::Incoming) {
            return match ($this) {
                self::Deposit => [ChequeStatus::InHand->value => ChequeStatus::UnderCollection],
                self::Collect => [ChequeStatus::InHand->value => ChequeStatus::Collected, ChequeStatus::UnderCollection->value => ChequeStatus::Collected],
                self::Bounce => [ChequeStatus::InHand->value => ChequeStatus::Bounced, ChequeStatus::UnderCollection->value => ChequeStatus::Bounced],
                self::Represent => [ChequeStatus::Bounced->value => ChequeStatus::InHand],
                self::Endorse => [ChequeStatus::InHand->value => ChequeStatus::Endorsed],
                self::Return => [ChequeStatus::InHand->value => ChequeStatus::Returned, ChequeStatus::Bounced->value => ChequeStatus::Returned],
                default => [],
            };
        }

        return match ($this) {
            self::Clear => [ChequeStatus::Issued->value => ChequeStatus::Cleared],
            self::Bounce => [ChequeStatus::Issued->value => ChequeStatus::Bounced],
            self::Cancel => [ChequeStatus::Issued->value => ChequeStatus::Cancelled, ChequeStatus::Bounced->value => ChequeStatus::Cancelled],
            self::Reissue => [ChequeStatus::Bounced->value => ChequeStatus::Issued],
            default => [],
        };
    }

    /**
     * The actions available to a cheque in its current status.
     *
     * @return list<self>
     */
    public static function availableFor(ChequeDirection $direction, ChequeStatus $status): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $action): bool => isset($action->transitions($direction)[$status->value]),
        ));
    }

    /**
     * Whether the action needs a bank account: to deposit or collect into.
     */
    public function needsBankAccount(): bool
    {
        return in_array($this, [self::Deposit, self::Collect], true);
    }
}
