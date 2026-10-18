<?php

namespace App\Modules\Contacts\Policies;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;

/**
 * Answers the standard abilities with the `Contact ...` permissions of the
 * PermissionSeeder.
 */
class ContactPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Contact';
    }

    /**
     * A contact with postings stays, so its statement remains intact:
     * deactivate it instead. Lists preload the check with
     * `withExists('journalLines')`.
     */
    public function delete(User $user, Contact $contact): bool
    {
        if ($contact->journal_lines_exists ?? $contact->journalLines()->exists()) {
            return false;
        }

        return $this->hasPermission($user, 'Delete');
    }
}
