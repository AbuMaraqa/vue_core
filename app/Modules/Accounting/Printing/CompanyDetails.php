<?php

namespace App\Modules\Accounting\Printing;

use App\Modules\Accounting\Settings\AccountingSettings;
use App\Modules\Foundation\Printing\LetterheadSource;

/**
 * Adds the company address and tax number, kept in the accounting settings,
 * to the letterhead of every printed document.
 */
class CompanyDetails implements LetterheadSource
{
    public function __construct(protected AccountingSettings $settings) {}

    public function details(): array
    {
        return [
            'Address' => $this->settings->company_address,
            'Tax number' => $this->settings->company_tax_number,
        ];
    }
}
