<?php

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\ExchangeRate;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Banking\Actions\BankAccounts\SaveBankAccount;
use App\Modules\Banking\Data\BankAccountData;
use App\Modules\Banking\Enums\BankAccountType;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\Cheque;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Support\ContactBalances;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Support\StockLedger;
use App\Modules\Invoicing\Enums\PaymentStatus;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Payment;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A small trading cycle with figures easy to check by hand:
 *
 *   bill:     10 chairs × 50 + 16% VAT        = 580 (inventory 500)
 *   invoice:   4 chairs × 100 − 10% + 16% VAT = 417.60 (revenue 360, cost 200)
 */
beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    seedAccounting();

    $this->jod = Currency::query()->where('code', 'JOD')->value('id');
    $this->vat = Tax::query()->where('rate', 16)->value('id');
    $this->warehouse = Warehouse::factory()->create();
    $this->chair = Item::factory()->create(['code' => 'CHAIR', 'purchase_price' => '50', 'sale_price' => '100']);
    $this->customer = Contact::factory()->create(['name' => 'Customer', 'payment_terms_days' => 30]);
    $this->vendor = Contact::factory()->vendor()->create(['name' => 'Vendor']);
    $this->cash = app(SaveBankAccount::class)->handle(new BankAccount, new BankAccountData(name: 'Cash', type: BankAccountType::Cash, currency_id: $this->jod));
});

function balanceOf(string $code): string
{
    return Decimal::round(app(AccountBalances::class)->asOf(now())[account($code)->id] ?? '0');
}

/**
 * @param  array<string, mixed>  $data
 * @param  list<array<string, mixed>>  $lines
 */
function document(string $route, array $data, array $lines, bool $post = true): Invoice
{
    $permission = str_contains($route, 'sales') ? 'Sales Invoice' : 'Purchase Bill';

    test()->actingAs(staffMember("{$permission} Create", "{$permission} Post"))
        ->post(route("{$route}.store"), [
            'date' => '2026-06-01',
            'currency_id' => test()->jod,
            'warehouse_id' => test()->warehouse->id,
            'lines' => $lines,
            'post' => $post,
            ...$data,
        ])
        ->assertSessionHasNoErrors();

    return Invoice::query()->latest('id')->firstOrFail();
}

function bill(): Invoice
{
    return document('admin.purchases.bills', ['contact_id' => test()->vendor->id, 'reference' => 'V-778'], [
        ['item_id' => test()->chair->id, 'quantity' => '10', 'unit_price' => '50', 'tax_id' => test()->vat],
    ]);
}

function saleOfFourChairs(): Invoice
{
    return document('admin.sales.invoices', ['contact_id' => test()->customer->id], [
        ['item_id' => test()->chair->id, 'quantity' => '4', 'unit_price' => '100', 'discount_percent' => '10', 'tax_id' => test()->vat],
    ]);
}

/**
 * @param  array<string, mixed>  $data
 */
function receipt(array $data): Payment
{
    test()->actingAs(staffMember('Customer Receipt Create'))
        ->post(route('admin.sales.receipts.store'), [
            'contact_id' => test()->customer->id,
            'date' => '2026-06-10',
            'method' => 'cash',
            'currency_id' => test()->jod,
            'bank_account_id' => test()->cash->id,
            ...$data,
        ])
        ->assertSessionHasNoErrors();

    return Payment::query()->latest('id')->firstOrFail();
}

describe('purchases and sales', function () {
    test('a bill brings the stock in at its value and owes the vendor', function () {
        $bill = bill();

        expect($bill)
            ->number->toStartWith('BILL-2026-')
            ->total->toBe('580.0000')
            ->and(balanceOf('1106'))->toBe('500.0000')
            ->and(balanceOf('1107'))->toBe('80.0000')
            ->and(app(ContactBalances::class)->balance($this->vendor))->toBe('-580.0000')
            ->and(app(StockLedger::class)->onHand($this->chair, $this->warehouse))->toBe('10.0000');
    });

    test('an invoice posts revenue, tax and the cost of the stock sold', function () {
        bill();
        $invoice = saleOfFourChairs();

        expect($invoice)
            ->number->toStartWith('INV-2026-')
            ->subtotal->toBe('360.0000')
            ->discount_total->toBe('40.0000')
            ->tax_total->toBe('57.6000')
            ->total->toBe('417.6000')
            ->due_date->toDateString()->toBe('2026-07-01')
            ->and($invoice->lines->sole()->unit_cost)->toBe('50.000000')
            ->and(balanceOf('4101'))->toBe('-360.0000')
            ->and(balanceOf('2103'))->toBe('-57.6000')
            ->and(balanceOf('5101'))->toBe('200.0000')
            ->and(balanceOf('1106'))->toBe('300.0000')
            ->and(app(ContactBalances::class)->balance($this->customer))->toBe('417.6000');
    });

    test('prices can include tax', function () {
        $service = Item::factory()->service()->create();

        $invoice = document('admin.sales.invoices', ['contact_id' => $this->customer->id, 'prices_include_tax' => true], [
            ['item_id' => $service->id, 'quantity' => '1', 'unit_price' => '116', 'tax_id' => $this->vat],
        ]);

        expect($invoice)->subtotal->toBe('100.0000')->tax_total->toBe('16.0000')->total->toBe('116.0000');
    });

    test('a draft has no effect until it is posted, and can be deleted', function () {
        $draft = document('admin.sales.invoices', ['contact_id' => $this->customer->id], [
            ['account_id' => account('4101')->id, 'description' => 'Consulting', 'quantity' => '2', 'unit_price' => '75'],
        ], post: false);

        expect($draft)->number->toBeNull()->status->toBe(DocumentStatus::Draft)
            ->and(JournalEntry::query()->exists())->toBeFalse();

        $this->actingAs(staffMember('Sales Invoice Post'))
            ->post(route('admin.sales.invoices.post', $draft))
            ->assertRedirect();

        expect($draft->fresh()->number)->toStartWith('INV-')
            ->and(balanceOf('4101'))->toBe('-150.0000');

        $other = document('admin.sales.invoices', ['contact_id' => $this->customer->id], [
            ['account_id' => account('4101')->id, 'quantity' => '1', 'unit_price' => '5'],
        ], post: false);

        $this->actingAs(staffMember('Sales Invoice Delete'))
            ->delete(route('admin.sales.invoices.destroy', $other))
            ->assertRedirect(route('admin.sales.invoices.index'));

        expect($other->fresh())->toBeNull();
    });

    test('stock items need a warehouse, and lines an item or an account', function () {
        $this->actingAs(staffMember('Sales Invoice Create'))
            ->post(route('admin.sales.invoices.store'), [
                'contact_id' => $this->customer->id,
                'date' => '2026-06-01',
                'currency_id' => $this->jod,
                'lines' => [['item_id' => $this->chair->id, 'quantity' => '1', 'unit_price' => '1'], ['quantity' => '1', 'unit_price' => '1']],
            ])
            ->assertSessionHasErrors(['warehouse_id', 'lines.1.account_id']);
    });

    test('a vendor cannot be invoiced as a customer', function () {
        $this->actingAs(staffMember('Sales Invoice Create'))
            ->post(route('admin.sales.invoices.store'), [
                'contact_id' => $this->vendor->id,
                'date' => '2026-06-01',
                'currency_id' => $this->jod,
                'lines' => [['account_id' => account('4101')->id, 'quantity' => '1', 'unit_price' => '1']],
            ])
            ->assertSessionHasErrors('contact_id');
    });

    test('a document is only reached through the routes of its type', function () {
        $bill = bill();

        $this->actingAs(staffMember('Sales Invoice View', 'Purchase Bill View'))
            ->get(route('admin.sales.invoices.show', $bill))
            ->assertNotFound();
    });
});

describe('returns', function () {
    beforeEach(function () {
        bill();
        $this->invoice = saleOfFourChairs();
    });

    test('a credit note returns the stock at the cost it left with', function () {
        $note = document('admin.sales.credit-notes', ['contact_id' => $this->customer->id, 'original_invoice_id' => $this->invoice->id], [
            ['item_id' => $this->chair->id, 'quantity' => '1', 'unit_price' => '100', 'discount_percent' => '10', 'tax_id' => $this->vat],
        ]);

        expect($note)->number->toStartWith('CN-2026-')->total->toBe('104.4000')
            ->and(balanceOf('1106'))->toBe('350.0000')
            ->and(balanceOf('5101'))->toBe('150.0000')
            ->and(app(ContactBalances::class)->balance($this->customer))->toBe('313.2000');

        $this->actingAs(staffMember('Sales Invoice Post'))
            ->post(route('admin.sales.invoices.apply-note', $this->invoice), ['note_id' => $note->id, 'amount' => '104.4'])
            ->assertSessionHasNoErrors();

        expect($this->invoice->fresh())->paid_amount->toBe('104.4000')->amountDue()->toBe('313.2000')
            ->and($note->fresh()->amountDue())->toBe('0.0000');
    });

    test('a debit note returns stock at average cost, the difference going to cost of sales', function () {
        document('admin.purchases.debit-notes', ['contact_id' => $this->vendor->id], [
            ['item_id' => $this->chair->id, 'quantity' => '2', 'unit_price' => '55'],
        ]);

        expect(balanceOf('1106'))->toBe('200.0000')
            ->and(balanceOf('5101'))->toBe('190.0000')
            ->and(app(ContactBalances::class)->balance($this->vendor))->toBe('-470.0000');
    });
});

describe('payments', function () {
    beforeEach(function () {
        bill();
        $this->invoice = saleOfFourChairs();
    });

    test('a receipt settles invoices, and the rest stays as credit', function () {
        $payment = receipt(['amount' => '500', 'allocations' => [['invoice_id' => $this->invoice->id, 'amount' => '417.6']]]);

        expect($payment)->number->toStartWith('RCT-2026-')
            ->and($this->invoice->fresh()->paymentStatus())->toBe(PaymentStatus::Paid)
            ->and($payment->fresh()->load('allocations')->unallocatedAmount())->toBe('82.4000')
            ->and(app(ContactBalances::class)->balance($this->customer))->toBe('-82.4000');
    });

    test('allocations cannot exceed the payment or the amount due', function () {
        $this->actingAs(staffMember('Customer Receipt Create'))
            ->post(route('admin.sales.receipts.store'), [
                'contact_id' => $this->customer->id, 'date' => '2026-06-10', 'method' => 'cash', 'currency_id' => $this->jod,
                'bank_account_id' => $this->cash->id, 'amount' => '100',
                'allocations' => [['invoice_id' => $this->invoice->id, 'amount' => '150']],
            ])
            ->assertSessionHasErrors('allocations');

        $payment = receipt(['amount' => '1000']);

        $this->actingAs(staffMember('Customer Receipt Create'))
            ->post(route('admin.sales.receipts.allocate', $payment), ['invoice_id' => $this->invoice->id, 'amount' => '500'])
            ->assertSessionHasErrors('accounting');
    });

    test('a bounced cheque opens the invoices it paid again', function () {
        $payment = receipt([
            'method' => 'cheque', 'bank_account_id' => null, 'amount' => '417.6', 'cheque_number' => '77001',
            'cheque_due_date' => '2026-07-15', 'drawee_bank' => 'Housing Bank',
            'allocations' => [['invoice_id' => $this->invoice->id, 'amount' => '417.6']],
        ]);
        $cheque = Cheque::query()->sole();

        expect($payment->cheque_id)->toBe($cheque->id)
            ->and($cheque->source_id)->toBe($payment->id)
            ->and(balanceOf('1104'))->toBe('417.6000')
            ->and($this->invoice->fresh()->paymentStatus())->toBe(PaymentStatus::Paid);

        $this->actingAs(staffMember('Cheque Manage'))
            ->post(route('admin.banking.cheques.perform', $cheque), ['action' => 'bounce', 'date' => '2026-06-12'])
            ->assertSessionHasNoErrors();

        expect($this->invoice->fresh()->paymentStatus())->toBe(PaymentStatus::Unpaid)
            ->and($payment->fresh()->load('allocations', 'cheque')->unallocatedAmount())->toBe('0.0000')
            ->and(app(ContactBalances::class)->balance($this->customer))->toBe('417.6000');
    });

    test('a paid invoice cannot be cancelled until the payment is removed', function () {
        $payment = receipt(['amount' => '417.6', 'allocations' => [['invoice_id' => $this->invoice->id, 'amount' => '417.6']]]);

        $this->actingAs(staffMember('Sales Invoice Cancel'))
            ->post(route('admin.sales.invoices.cancel', $this->invoice))
            ->assertSessionHasErrors('accounting');

        $this->actingAs(staffMember('Customer Receipt Cancel'))
            ->post(route('admin.sales.receipts.cancel', $payment))
            ->assertRedirect();

        $this->actingAs(staffMember('Sales Invoice Cancel'))
            ->post(route('admin.sales.invoices.cancel', $this->invoice))
            ->assertRedirect();

        expect($this->invoice->fresh()->status)->toBe(DocumentStatus::Cancelled)
            ->and(balanceOf('4101'))->toBe('0.0000')
            ->and(balanceOf('5101'))->toBe('0.0000')
            ->and(app(StockLedger::class)->onHand($this->chair))->toBe('10.0000')
            ->and(app(ContactBalances::class)->balance($this->customer))->toBe('0.0000');
    });

    test('a payment at another exchange rate realizes the difference', function () {
        $usd = Currency::query()->where('code', 'USD')->sole();
        ExchangeRate::query()->create(['currency_id' => $usd->id, 'effective_date' => '2026-06-01', 'rate' => '0.709']);
        ExchangeRate::query()->create(['currency_id' => $usd->id, 'effective_date' => '2026-06-10', 'rate' => '0.710']);

        $invoice = document('admin.sales.invoices', ['contact_id' => $this->customer->id, 'currency_id' => $usd->id], [
            ['account_id' => account('4101')->id, 'quantity' => '1', 'unit_price' => '1000'],
        ]);

        receipt(['amount' => '1000', 'currency_id' => $usd->id, 'allocations' => [['invoice_id' => $invoice->id, 'amount' => '1000']]]);

        expect(balanceOf('4202'))->toBe('-1.0000')
            ->and(app(ContactBalances::class)->balance($this->customer))->toBe('417.6000');
    });

    test('the receipt prints as a PDF for staff members who can view it', function () {
        $payment = receipt(['amount' => '500', 'reference' => 'TR-1', 'allocations' => [['invoice_id' => $this->invoice->id, 'amount' => '417.6']]]);

        $response = $this->actingAs(staffMember('Customer Receipt View'))
            ->get(route('admin.sales.receipts.pdf', $payment))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        expect($response->getContent())->toStartWith('%PDF');

        $this->actingAs(staffMember('Vendor Payment View'))
            ->get(route('admin.sales.receipts.pdf', $payment))
            ->assertForbidden();
    });
});

describe('opening balances and reports', function () {
    test('an opening invoice carries a balance against opening equity, and ages', function () {
        $opening = document('admin.sales.invoices', ['contact_id' => $this->customer->id, 'is_opening' => true, 'date' => '2026-01-01', 'due_date' => '2026-04-01'], [
            ['description' => 'Opening balance', 'quantity' => '1', 'unit_price' => '900'],
        ]);

        expect($opening->total)->toBe('900.0000')
            ->and(balanceOf('3103'))->toBe('-900.0000');

        $this->actingAs(staffMember('Invoicing Report View'))
            ->get(route('admin.invoicing.reports.aging', ['side' => 'receivables', 'as_of' => '2026-06-15']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/invoicing/reports/Aging')
                ->where('report.contacts.0.61_90', '900.0000')
                ->where('report.totals.total', '900.0000'));
    });

    test('sales by item show the gross margin', function () {
        bill();
        saleOfFourChairs();

        $this->actingAs(staffMember('Invoicing Report View'))
            ->get(route('admin.invoicing.reports.analysis', ['side' => 'sales', 'group_by' => 'item', 'from' => '2026-06-01', 'to' => '2026-06-30']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/invoicing/reports/Analysis')
                ->where('report.rows.0.code', 'CHAIR')
                ->where('report.rows.0.net', '360.0000')
                ->where('report.rows.0.cost', '200.0000')
                ->where('report.rows.0.margin', '160.0000'));
    });

    test('the entry of an invoice links back to it', function () {
        bill();
        $invoice = saleOfFourChairs();
        $entry = JournalEntry::query()->where('source_type', $invoice->getMorphClass())->where('source_id', $invoice->id)->firstOrFail();

        $this->actingAs(staffMember('Journal Entry View List'))
            ->get(route('admin.accounting.journal-entries.show', $entry))
            ->assertInertia(fn (Assert $page) => $page
                ->where('entry.data.source.document.label', 'Sales invoice')
                ->where('entry.data.source.document.url', route('admin.sales.invoices.show', $invoice)));
    });

    test('the invoice prints as a PDF', function () {
        bill();
        $invoice = saleOfFourChairs();

        $this->actingAs(staffMember('Sales Invoice View'))
            ->get(route('admin.sales.invoices.pdf', $invoice))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    });
});
