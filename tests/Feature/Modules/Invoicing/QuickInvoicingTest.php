<?php

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Banking\Actions\BankAccounts\SaveBankAccount;
use App\Modules\Banking\Data\BankAccountData;
use App\Modules\Banking\Enums\BankAccountType;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Support\ContactBalances;
use App\Modules\Inventory\Models\Item;
use App\Modules\Invoicing\Enums\PaymentMethod;
use App\Modules\Invoicing\Enums\PaymentStatus;
use App\Modules\Invoicing\Enums\PaymentType;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Payment;

/**
 * The shortcuts of the invoice form: "paid in full now" and "save and new".
 * A service at 50 × 2 makes documents of 100 without stock or tax.
 */
beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    seedAccounting();

    $this->jod = Currency::query()->where('code', 'JOD')->value('id');
    $this->service = Item::factory()->service()->create();
    $this->customer = Contact::factory()->create();
    $this->vendor = Contact::factory()->vendor()->create();
    $this->cash = app(SaveBankAccount::class)->handle(new BankAccount, new BankAccountData(name: 'Cash', type: BankAccountType::Cash, currency_id: $this->jod));
    $this->document = fn (array $data): array => [
        'date' => '2026-06-01',
        'currency_id' => $this->jod,
        'lines' => [['item_id' => $this->service->id, 'quantity' => '2', 'unit_price' => '50']],
        ...$data,
    ];
});

describe('paid in full now', function () {
    test('posting a sales invoice paid now records a receipt allocated to it', function () {
        $this->actingAs(staffMember('Sales Invoice Create', 'Sales Invoice Post', 'Customer Receipt Create'))
            ->post(route('admin.sales.invoices.store'), ($this->document)([
                'contact_id' => $this->customer->id, 'post' => true, 'paid_now' => true, 'payment_account_id' => $this->cash->id,
            ]))
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->sole();
        $payment = Payment::query()->with('allocations')->sole();

        expect($invoice->status)->toBe(DocumentStatus::Posted)
            ->and($invoice->paymentStatus())->toBe(PaymentStatus::Paid)
            ->and($payment)
            ->type->toBe(PaymentType::Receipt)
            ->status->toBe(DocumentStatus::Posted)
            ->method->toBe(PaymentMethod::Cash)
            ->bank_account_id->toBe($this->cash->id)
            ->amount->toBe('100.0000')
            ->and($payment->allocations->sole())
            ->invoice_id->toBe($invoice->id)
            ->amount->toBe('100.0000')
            ->and(Decimal::round(app(AccountBalances::class)->asOf(now())[$this->cash->account_id] ?? '0'))->toBe('100.0000')
            ->and(app(ContactBalances::class)->balance($this->customer))->toBe('0.0000');
    });

    test('posting a purchase bill paid now records a vendor payment', function () {
        $this->actingAs(staffMember('Purchase Bill Create', 'Purchase Bill Post', 'Vendor Payment Create'))
            ->post(route('admin.purchases.bills.store'), ($this->document)([
                'contact_id' => $this->vendor->id, 'post' => true, 'paid_now' => true, 'payment_account_id' => $this->cash->id,
            ]))
            ->assertSessionHasNoErrors();

        expect(Invoice::query()->sole()->paymentStatus())->toBe(PaymentStatus::Paid)
            ->and(Payment::query()->sole()->type)->toBe(PaymentType::Payment)
            ->and(app(ContactBalances::class)->balance($this->vendor))->toBe('0.0000');
    });

    test('it needs a cash or bank account in the currency of the document', function () {
        $user = staffMember('Sales Invoice Create', 'Sales Invoice Post', 'Customer Receipt Create');
        $usd = Currency::query()->where('code', 'USD')->value('id');
        $dollars = app(SaveBankAccount::class)->handle(new BankAccount, new BankAccountData(name: 'Dollars', type: BankAccountType::Bank, currency_id: $usd));

        $this->actingAs($user)
            ->post(route('admin.sales.invoices.store'), ($this->document)(['contact_id' => $this->customer->id, 'post' => true, 'paid_now' => true]))
            ->assertSessionHasErrors('payment_account_id');

        $this->actingAs($user)
            ->post(route('admin.sales.invoices.store'), ($this->document)([
                'contact_id' => $this->customer->id, 'post' => true, 'paid_now' => true, 'payment_account_id' => $dollars->id,
            ]))
            ->assertSessionHasErrors('payment_account_id');

        expect(Invoice::query()->count())->toBe(0)
            ->and(Payment::query()->count())->toBe(0);
    });

    test('a draft ignores it', function () {
        $this->actingAs(staffMember('Sales Invoice Create'))
            ->post(route('admin.sales.invoices.store'), ($this->document)(['contact_id' => $this->customer->id, 'post' => false, 'paid_now' => true]))
            ->assertSessionHasNoErrors();

        expect(Invoice::query()->sole()->status)->toBe(DocumentStatus::Draft)
            ->and(Payment::query()->count())->toBe(0);
    });

    test('it needs the permission to record receipts', function () {
        $this->actingAs(staffMember('Sales Invoice Create', 'Sales Invoice Post'))
            ->post(route('admin.sales.invoices.store'), ($this->document)([
                'contact_id' => $this->customer->id, 'post' => true, 'paid_now' => true, 'payment_account_id' => $this->cash->id,
            ]))
            ->assertForbidden();

        expect(Invoice::query()->count())->toBe(0)
            ->and(Payment::query()->count())->toBe(0);
    });
});

describe('save and new', function () {
    test('it posts the document and opens a fresh form of the same type', function () {
        $this->actingAs(staffMember('Sales Invoice Create', 'Sales Invoice Post'))
            ->post(route('admin.sales.invoices.store'), ($this->document)(['contact_id' => $this->customer->id, 'post' => true, 'and_new' => true]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.sales.invoices.create'));

        expect(Invoice::query()->sole()->status)->toBe(DocumentStatus::Posted);
    });

    test('a draft of a bill opens a new bill form', function () {
        $this->actingAs(staffMember('Purchase Bill Create'))
            ->post(route('admin.purchases.bills.store'), ($this->document)(['contact_id' => $this->vendor->id, 'and_new' => true]))
            ->assertRedirect(route('admin.purchases.bills.create'));

        expect(Invoice::query()->sole()->status)->toBe(DocumentStatus::Draft);
    });
});
