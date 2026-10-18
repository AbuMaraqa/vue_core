<?php

namespace App\Modules\Invoicing\Policies;

use App\Models\User;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Invoicing\Models\Payment;

/**
 * Receipts use the `Customer Receipt ...` permissions and payments the
 * `Vendor Payment ...` ones. Payments are posted when saved; allocating
 * them is part of creating them.
 */
class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return $this->allows($user, $payment, 'View');
    }

    public function allocate(User $user, Payment $payment): bool
    {
        return $payment->isEffective() && $this->allows($user, $payment, 'Create');
    }

    public function cancel(User $user, Payment $payment): bool
    {
        return $payment->status === DocumentStatus::Posted && $payment->cheque_id === null && $this->allows($user, $payment, 'Cancel');
    }

    protected function allows(User $user, Payment $payment, string $action): bool
    {
        return $user->can("{$payment->type->permissionPrefix()} {$action}");
    }
}
