<?php

use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Contacts\Models\Contact;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    seedAccounting();
});

/**
 * A two-line entry where the receivable or payable side names the contact.
 */
function postWithContact(Contact $contact, string $date, string $debitCode, string $creditCode, string $amount): void
{
    $partnerSide = in_array($debitCode, ['1103', '2101'], true);

    JournalEntryBuilder::make($date, 'Test')
        ->debit(account($debitCode), $amount, partner: $partnerSide ? $contact : null)
        ->credit(account($creditCode), $amount, partner: $partnerSide ? null : $contact)
        ->post();
}

/**
 * @param  list<list<string>>  $rows
 */
function contactsCsv(array $rows): UploadedFile
{
    $lines = array_map(fn (array $row): string => implode(',', $row), [['name', 'type', 'code', 'currency', 'email'], ...$rows]);

    return UploadedFile::fake()->createWithContent('contacts.csv', implode("\n", $lines));
}

describe('list', function () {
    test('staff members without the permission are forbidden', function () {
        $this->actingAs(staffMember())
            ->get(route('admin.contacts.index'))
            ->assertForbidden();
    });

    test('contacts are filtered by type and search, with their balance', function () {
        $customer = Contact::factory()->create(['name' => 'Alpha Trading']);
        Contact::factory()->vendor()->create(['name' => 'Alpha Supplies']);
        Contact::factory()->create(['name' => 'Beta Trading']);
        postWithContact($customer, '2026-06-01', '1103', '4101', '250');

        $this->actingAs(staffMember('Contact View List'))
            ->get(route('admin.contacts.index', ['filter' => ['search' => 'Alpha', 'type' => 'customers']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/contacts/Index')
                ->has('contacts.data', 1)
                ->where('contacts.data.0.name', 'Alpha Trading')
                ->where('contacts.data.0.balance', '250.0000')
                ->where('contacts.data.0.can.delete', false),
            );
    });

    test('the list is exported', function () {
        Contact::factory()->create(['name' => 'Alpha']);

        $this->actingAs(staffMember('Contact Export'))
            ->get(route('admin.contacts.export', ['format' => 'csv']))
            ->assertOk()
            ->assertDownload();
    });
});

describe('create and update', function () {
    test('a customer gets a code from its id when none is given', function () {
        $this->actingAs(staffMember('Contact Create'))
            ->post(route('admin.contacts.store'), [
                'kind' => 'company',
                'name' => 'Alpha Trading',
                'is_customer' => true,
                'payment_terms_days' => 30,
                'credit_limit' => '5000',
            ])
            ->assertSessionHasNoErrors();

        $contact = Contact::query()->sole();

        expect($contact)
            ->code->toBe(sprintf('C-%05d', $contact->id))
            ->is_customer->toBeTrue()
            ->is_vendor->toBeFalse()
            ->payment_terms_days->toBe(30)
            ->credit_limit->toBe('5000.0000');
    });

    test('a generated code skips a code typed by hand', function () {
        $next = (int) Contact::query()->max('id') + 2;
        Contact::factory()->create(['code' => sprintf('C-%05d', $next)]);

        $this->actingAs(staffMember('Contact Create'))
            ->post(route('admin.contacts.store'), ['kind' => 'company', 'name' => 'Second', 'is_vendor' => true])
            ->assertSessionHasNoErrors();

        expect(Contact::query()->where('name', 'Second')->value('code'))->toBe(sprintf('C-%05d-1', $next));
    });

    test('a contact must be a customer or a vendor, with accounts of the right kind', function () {
        $expense = Account::factory()->create();

        $this->actingAs(staffMember('Contact Create'))
            ->post(route('admin.contacts.store'), [
                'kind' => 'company',
                'name' => 'Nobody',
                'receivable_account_id' => $expense->id,
            ])
            ->assertSessionHasErrors(['is_customer', 'receivable_account_id']);

        expect(Contact::query()->exists())->toBeFalse();
    });

    test('codes are unique', function () {
        Contact::factory()->create(['code' => 'CUS-1']);

        $this->actingAs(staffMember('Contact Create'))
            ->post(route('admin.contacts.store'), ['kind' => 'company', 'name' => 'Copy', 'code' => 'CUS-1', 'is_customer' => true])
            ->assertSessionHasErrors('code');
    });

    test('a contact is updated and keeps its code', function () {
        $contact = Contact::factory()->create(['code' => 'CUS-7']);
        $receivable = Account::factory()->subtype(AccountSubtype::Receivable)->create();

        $this->actingAs(staffMember('Contact Update'))
            ->put(route('admin.contacts.update', $contact), [
                'kind' => 'individual',
                'name' => 'Renamed',
                'is_customer' => true,
                'is_vendor' => true,
                'receivable_account_id' => $receivable->id,
            ])
            ->assertRedirect(route('admin.contacts.show', $contact));

        expect($contact->fresh())
            ->code->toBe('CUS-7')
            ->name->toBe('Renamed')
            ->is_vendor->toBeTrue()
            ->receivableAccountId()->toBe($receivable->id)
            ->payableAccountId()->toBe(account('2101')->id);
    });
});

describe('delete', function () {
    test('a contact without postings is deleted', function () {
        $contact = Contact::factory()->create();

        $this->actingAs(staffMember('Contact Delete'))
            ->delete(route('admin.contacts.destroy', $contact))
            ->assertRedirect(route('admin.contacts.index'));

        expect($contact->fresh())->toBeNull();
    });

    test('a contact with postings is kept', function () {
        $contact = Contact::factory()->create();
        postWithContact($contact, '2026-06-01', '1103', '4101', '100');

        $this->actingAs(staffMember('Contact Delete'))
            ->delete(route('admin.contacts.destroy', $contact))
            ->assertForbidden();

        expect($contact->fresh())->not->toBeNull();
    });
});

describe('statement', function () {
    beforeEach(function () {
        $this->contact = Contact::factory()->create(['is_vendor' => true]);

        postWithContact($this->contact, '2026-03-10', '1103', '4101', '1000');
        postWithContact($this->contact, '2026-04-05', '110101', '1103', '400');
        postWithContact($this->contact, '2026-05-20', '5101', '2101', '300');
        postWithContact($this->contact, '2026-05-21', '1103', '4101', '999');
    });

    test('it starts from the balance before the period and runs to the closing balance', function () {
        $this->actingAs(staffMember('Contact View'))
            ->get(route('admin.contacts.show', [$this->contact, 'from' => '2026-04-01', 'to' => '2026-05-20']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/contacts/Show')
                ->where('statement.opening', '1000.0000')
                ->has('statement.lines', 2)
                ->where('statement.lines.0.credit', '400.0000')
                ->where('statement.lines.0.balance', '600.0000')
                ->where('statement.lines.1.balance', '300.0000')
                ->where('statement.closing', '300.0000')
                ->where('balances.receivable', '1599.0000')
                ->where('balances.payable', '300.0000')
                ->where('balances.balance', '1299.0000'),
            );
    });

    test('lines on other accounts, such as cash, are not part of the statement', function () {
        JournalEntryBuilder::make('2026-06-01', 'Cash with the contact named')
            ->debit(account('110101'), '50', partner: $this->contact)
            ->credit(account('4101'), '50')
            ->post();

        $this->actingAs(staffMember('Contact View'))
            ->get(route('admin.contacts.show', $this->contact))
            ->assertInertia(fn (Assert $page) => $page->where('balances.balance', '1299.0000'));
    });

    test('downloading the statement needs the export permission', function () {
        $this->actingAs(staffMember('Contact View'))
            ->get(route('admin.contacts.show', [$this->contact, 'format' => 'csv']))
            ->assertForbidden();

        $this->actingAs(staffMember('Contact View', 'Contact Export'))
            ->get(route('admin.contacts.show', [$this->contact, 'format' => 'csv']))
            ->assertOk()
            ->assertDownload();
    });

    test('the statement prints as a PDF for staff members who can view the contact', function () {
        $response = $this->actingAs(staffMember('Contact View'))
            ->get(route('admin.contacts.pdf', [$this->contact, 'from' => '2026-04-01', 'to' => '2026-05-20']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        expect($response->getContent())->toStartWith('%PDF');

        $this->actingAs(staffMember('Contact View List'))
            ->get(route('admin.contacts.pdf', $this->contact))
            ->assertForbidden();
    });
});

describe('import', function () {
    test('valid rows are imported and invalid rows are reported', function () {
        Contact::factory()->create(['code' => 'TAKEN']);

        $this->actingAs(staffMember('Contact Import'))
            ->from(route('admin.contacts.index'))
            ->post(route('admin.contacts.import'), ['file' => contactsCsv([
                ['Alpha Trading', 'customer', '', 'USD', 'alpha@example.com'],
                ['Beta Supplies', 'both', 'BETA', '', ''],
                ['No Type', '', '', '', ''],
                ['Taken Code', 'vendor', 'TAKEN', '', ''],
                ['Unknown Currency', 'vendor', '', 'XYZ', ''],
            ])])
            ->assertRedirect(route('admin.contacts.index'))
            ->assertInertiaFlash('importResult.imported', 2)
            ->assertInertiaFlash('importResult.failures.0.row', 5)
            ->assertInertiaFlash('importResult.failures.0.column', 'code')
            ->assertInertiaFlash('importResult.failures.1.row', 4)
            ->assertInertiaFlash('importResult.failures.1.column', 'type')
            ->assertInertiaFlash('importResult.failures.2.row', 6)
            ->assertInertiaFlash('importResult.failures.2.column', 'currency');

        expect(Contact::query()->where('name', 'Alpha Trading')->sole())
            ->is_customer->toBeTrue()
            ->is_vendor->toBeFalse()
            ->currency->code->toBe('USD')
            ->and(Contact::query()->where('code', 'BETA')->sole())
            ->is_customer->toBeTrue()
            ->is_vendor->toBeTrue();
    });
});

describe('quick store', function () {
    test('a contact created from a document form comes back as a picker option', function () {
        $response = $this->actingAs(staffMember('Contact Create'))
            ->postJson(route('admin.contacts.quick-store'), [
                'name' => 'Walk-in Customer', 'mobile' => '0790000000', 'kind' => 'individual', 'is_customer' => true, 'is_vendor' => false,
            ])
            ->assertCreated();

        $contact = Contact::query()->sole();

        $response->assertExactJson([
            'value' => $contact->id,
            'label' => 'Walk-in Customer',
            'hint' => $contact->code,
            'currency_id' => null,
            'payment_terms_days' => 0,
        ]);

        expect($contact)
            ->code->toStartWith('C-')
            ->mobile->toBe('0790000000')
            ->is_customer->toBeTrue()
            ->is_vendor->toBeFalse();
    });

    test('it is validated like the contact form', function () {
        $this->actingAs(staffMember('Contact Create'))
            ->postJson(route('admin.contacts.quick-store'), ['name' => '', 'kind' => 'individual', 'is_customer' => false, 'is_vendor' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'is_customer']);
    });

    test('staff members without the permission are forbidden', function () {
        $this->actingAs(staffMember())
            ->postJson(route('admin.contacts.quick-store'), ['name' => 'Someone', 'kind' => 'individual', 'is_customer' => true])
            ->assertForbidden();

        expect(Contact::query()->count())->toBe(0);
    });
});
