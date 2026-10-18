<?php

namespace App\Modules\Invoicing\Policies;

use App\Models\User;
use App\Modules\Invoicing\Models\Quotation;

/**
 * Quotations use the `Sales Quotation ...` permissions. Converting one also
 * creates a sales invoice, so it needs that permission too.
 */
class QuotationPolicy
{
    public function view(User $user, Quotation $quotation): bool
    {
        return $user->can('Sales Quotation View');
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $quotation->isEditable() && $user->can('Sales Quotation Update');
    }

    /**
     * A quotation that became an invoice stays as its history.
     */
    public function delete(User $user, Quotation $quotation): bool
    {
        return ! $quotation->isInvoiced() && $user->can('Sales Quotation Delete');
    }

    /**
     * Mark it sent, accepted or rejected.
     */
    public function changeStatus(User $user, Quotation $quotation): bool
    {
        return ! $quotation->isInvoiced() && $user->can('Sales Quotation Update');
    }

    public function convert(User $user, Quotation $quotation): bool
    {
        return $quotation->isConvertible()
            && $user->can('Sales Quotation Convert')
            && $user->can('Sales Invoice Create');
    }
}
