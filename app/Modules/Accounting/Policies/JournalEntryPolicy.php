<?php

namespace App\Modules\Accounting\Policies;

use App\Models\User;
use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;

/**
 * Manual entries are edited and deleted only while they are drafts. Entries
 * written by a document (invoice, payment...) belong to that document: they
 * change only when the document is voided.
 */
class JournalEntryPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Journal Entry';
    }

    public function view(User $user): bool
    {
        return $this->hasPermission($user, 'View List');
    }

    public function update(User $user, JournalEntry $entry): bool
    {
        return $this->isManualDraft($entry) && $this->hasPermission($user, 'Update');
    }

    public function delete(User $user, JournalEntry $entry): bool
    {
        return $this->isManualDraft($entry) && $this->hasPermission($user, 'Delete');
    }

    public function post(User $user, JournalEntry $entry): bool
    {
        return $this->isManualDraft($entry) && $this->hasPermission($user, 'Post');
    }

    public function reverse(User $user, JournalEntry $entry): bool
    {
        return $entry->status === JournalEntryStatus::Posted
            && $entry->source_type === null
            && $entry->reversal_of_id === null
            && $this->hasPermission($user, 'Reverse');
    }

    protected function isManualDraft(JournalEntry $entry): bool
    {
        return $entry->isDraft() && $entry->source_type === null;
    }
}
