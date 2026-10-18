<?php

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Invoicing\Enums\QuotationStatus;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Quotation;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The figures of the invoicing tests: 4 chairs × 100 − 10% + 16% VAT
 * = 360 + 57.60 = 417.60, plus a delivery line without an item.
 */
beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    seedAccounting();

    $this->jod = Currency::query()->where('code', 'JOD')->value('id');
    $this->vat = Tax::query()->where('rate', 16)->value('id');
    $this->warehouse = Warehouse::factory()->create();
    $this->chair = Item::factory()->create(['code' => 'CHAIR', 'sale_price' => '100']);
    $this->customer = Contact::factory()->create(['name' => 'Customer']);
});

/**
 * @param  array<string, mixed>  $data
 */
function quoteChairs(array $data = []): Quotation
{
    test()->actingAs(staffMember('Sales Quotation Create'))
        ->post(route('admin.sales.quotations.store'), [
            'contact_id' => test()->customer->id,
            'date' => '2026-06-15',
            'currency_id' => test()->jod,
            'lines' => [
                ['item_id' => test()->chair->id, 'quantity' => '4', 'unit_price' => '100', 'discount_percent' => '10', 'tax_id' => test()->vat],
                ['description' => 'Delivery', 'quantity' => '1', 'unit_price' => '20'],
            ],
            ...$data,
        ])
        ->assertSessionHasNoErrors();

    return Quotation::query()->latest('id')->firstOrFail();
}

describe('store', function () {
    test('a quotation is numbered and priced like an invoice, without touching the books', function () {
        $quotation = quoteChairs();

        expect($quotation)
            ->number->toBe('QT-2026-00001')
            ->status->toBe(QuotationStatus::Draft)
            ->subtotal->toBe('380.0000')
            ->tax_total->toBe('57.6000')
            ->total->toBe('437.6000')
            ->and($quotation->valid_until->toDateString())->toBe('2026-07-15')
            ->and($quotation->lines)->toHaveCount(2)
            ->and($quotation->lines[0]->description)->toBe($this->chair->translate('name'))
            ->and(JournalEntry::query()->count())->toBe(0);
    });

    test('every line needs an item or a description', function () {
        $this->actingAs(staffMember('Sales Quotation Create'))
            ->post(route('admin.sales.quotations.store'), [
                'contact_id' => $this->customer->id,
                'date' => '2026-06-15',
                'currency_id' => $this->jod,
                'lines' => [['quantity' => '1', 'unit_price' => '20']],
            ])
            ->assertSessionHasErrors('lines.0.description');
    });

    test('a quotation is only made for a customer', function () {
        $vendor = Contact::factory()->vendor()->create();

        $this->actingAs(staffMember('Sales Quotation Create'))
            ->post(route('admin.sales.quotations.store'), [
                'contact_id' => $vendor->id,
                'date' => '2026-06-15',
                'currency_id' => $this->jod,
                'lines' => [['description' => 'Table', 'quantity' => '1', 'unit_price' => '20']],
            ])
            ->assertSessionHasErrors('contact_id');
    });

    test('staff without the permission cannot create one', function () {
        $this->actingAs(staffMember())
            ->post(route('admin.sales.quotations.store'), [])
            ->assertForbidden();
    });
});

describe('index', function () {
    test('quotations past their validity date are listed as expired', function () {
        quoteChairs(['valid_until' => '2026-06-20']);
        $this->travelTo('2026-06-21 10:00:00');

        $this->actingAs(staffMember('Sales Quotation View List'))
            ->get(route('admin.sales.quotations.index', ['filter' => ['status' => 'expired']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/invoicing/quotations/Index')
                ->has('quotations.data', 1)
                ->where('quotations.data.0.status', 'expired')
                ->where('summary.expired', 1));
    });
});

describe('status', function () {
    test('the customer\'s answer is recorded', function () {
        $quotation = quoteChairs();

        $this->actingAs(staffMember('Sales Quotation Update'))
            ->post(route('admin.sales.quotations.status', $quotation), ['status' => 'sent'])
            ->assertSessionHasNoErrors();

        expect($quotation->fresh()->status)->toBe(QuotationStatus::Sent);
    });

    test('a rejected quotation cannot be accepted without being offered again', function () {
        $quotation = quoteChairs();
        $quotation->update(['status' => QuotationStatus::Rejected]);

        $this->actingAs(staffMember('Sales Quotation Update'))
            ->post(route('admin.sales.quotations.status', $quotation), ['status' => 'accepted'])
            ->assertSessionHasErrors('accounting');

        expect($quotation->fresh()->status)->toBe(QuotationStatus::Rejected);
    });
});

describe('convert', function () {
    test('a quotation becomes a draft sales invoice with the same lines and total', function () {
        $quotation = quoteChairs();

        $this->actingAs(staffMember('Sales Quotation Convert', 'Sales Invoice Create'))
            ->post(route('admin.sales.quotations.convert', $quotation))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.sales.invoices.edit', Invoice::query()->latest('id')->firstOrFail()));

        $invoice = Invoice::query()->with('lines')->latest('id')->firstOrFail();
        $quotation->refresh();

        expect($invoice)
            ->type->toBe(InvoiceType::SalesInvoice)
            ->status->toBe(DocumentStatus::Draft)
            ->contact_id->toBe($this->customer->id)
            ->warehouse_id->toBe($this->warehouse->id)
            ->reference->toBe('QT-2026-00001')
            ->total->toBe('437.6000')
            ->and($invoice->lines[1]->account_id)->toBe(app(DefaultAccounts::class)->id(AccountRole::Sales))
            ->and($quotation->status)->toBe(QuotationStatus::Accepted)
            ->and($quotation->invoice_id)->toBe($invoice->id)
            ->and(JournalEntry::query()->count())->toBe(0);
    });

    test('a quotation is converted only once, and is locked afterwards', function () {
        $quotation = quoteChairs();
        $user = staffMember('Sales Quotation Convert', 'Sales Invoice Create', 'Sales Quotation Update', 'Sales Quotation Delete');

        $this->actingAs($user)->post(route('admin.sales.quotations.convert', $quotation));

        $this->actingAs($user)->post(route('admin.sales.quotations.convert', $quotation))->assertForbidden();
        $this->actingAs($user)->get(route('admin.sales.quotations.edit', $quotation))->assertForbidden();
        $this->actingAs($user)->delete(route('admin.sales.quotations.destroy', $quotation))->assertForbidden();

        expect(Invoice::query()->count())->toBe(1);
    });

    test('deleting the draft invoice frees the quotation again', function () {
        $quotation = quoteChairs();
        $this->actingAs(staffMember('Sales Quotation Convert', 'Sales Invoice Create'))
            ->post(route('admin.sales.quotations.convert', $quotation));

        Invoice::query()->firstOrFail()->delete();

        expect($quotation->fresh())
            ->isInvoiced()->toBeFalse()
            ->isConvertible()->toBeTrue();
    });

    test('converting needs the permission to create sales invoices', function () {
        $quotation = quoteChairs();

        $this->actingAs(staffMember('Sales Quotation Convert'))
            ->post(route('admin.sales.quotations.convert', $quotation))
            ->assertForbidden();
    });
});

test('the quotation prints as a PDF', function () {
    $quotation = quoteChairs();

    $this->actingAs(staffMember('Sales Quotation View'))
        ->get(route('admin.sales.quotations.pdf', $quotation))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});
