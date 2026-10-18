<?php

namespace App\Modules\Foundation\Printing;

/**
 * Adds details owned by a module to the letterhead, e.g. the accounting
 * module adds the company address and tax number.
 */
interface LetterheadSource
{
    /**
     * The details as English translation keys mapped to their values. Empty
     * values are left out of the letterhead.
     *
     * @return array<string, string|null>
     */
    public function details(): array;
}
