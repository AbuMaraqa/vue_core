<?php

namespace App\Modules\Invoicing\Policies;

use App\Models\User;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Invoicing\Models\Invoice;

/**
 * Sales documents use the `Sales Invoice ...` permissions and purchase
 * documents the `Purchase Bill ...` ones. Drafts are edited, deleted and
 * posted; posted documents are only cancelled.
 */
class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $this->allows($user, $invoice, 'View');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft() && $this->allows($user, $invoice, 'Update');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft() && $this->allows($user, $invoice, 'Delete');
    }

    public function post(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft() && $this->allows($user, $invoice, 'Post');
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $invoice->status === DocumentStatus::Posted && $this->allows($user, $invoice, 'Cancel');
    }

    /**
     * Applying notes and removing settlements change what is due, like
     * posting does.
     */
    public function settle(User $user, Invoice $invoice): bool
    {
        return $invoice->status === DocumentStatus::Posted && $this->allows($user, $invoice, 'Post');
    }

    protected function allows(User $user, Invoice $invoice, string $action): bool
    {
        return $user->can("{$invoice->type->permissionPrefix()} {$action}");
    }
}
