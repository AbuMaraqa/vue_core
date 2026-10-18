<?php

namespace App\Modules\Contacts\Exports;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Foundation\Exports\Exporter;

/**
 * The contacts list as a spreadsheet or PDF, with the columns of the import
 * template so an export can be edited and imported elsewhere.
 *
 * @extends Exporter<Contact>
 */
class ContactsExporter extends Exporter
{
    public function title(): string
    {
        return 'Contacts';
    }

    public function columns(): array
    {
        return [
            'Code' => fn (Contact $contact): string => $contact->code,
            'Name' => fn (Contact $contact): string => $contact->name,
            'Type' => fn (Contact $contact): string => match (true) {
                $contact->is_customer && $contact->is_vendor => __('Customer and vendor'),
                $contact->is_vendor => __('Vendor'),
                default => __('Customer'),
            },
            'Kind' => fn (Contact $contact): string => $contact->kind->label(),
            'Contact person' => fn (Contact $contact): ?string => $contact->contact_person,
            'Email' => fn (Contact $contact): ?string => $contact->email,
            'Phone' => fn (Contact $contact): ?string => $contact->phone,
            'Mobile' => fn (Contact $contact): ?string => $contact->mobile,
            'Tax number' => fn (Contact $contact): ?string => $contact->tax_number,
            'Currency' => fn (Contact $contact): ?string => $contact->currency?->code,
            'Payment terms' => fn (Contact $contact): int => $contact->payment_terms_days,
            'Credit limit' => fn (Contact $contact): ?string => $contact->credit_limit !== null ? (string) $contact->credit_limit : null,
            'City' => fn (Contact $contact): ?string => $contact->city,
            'Country' => fn (Contact $contact): ?string => $contact->country,
            'Address' => fn (Contact $contact): ?string => $contact->address,
            'Status' => fn (Contact $contact): string => $contact->is_active ? __('Active') : __('Inactive'),
        ];
    }
}
