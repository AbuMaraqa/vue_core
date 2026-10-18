<?php

use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Banking\Actions\BankAccounts\SaveBankAccount;
use App\Modules\Banking\Data\BankAccountData;
use App\Modules\Banking\Enums\BankAccountType;
use App\Modules\Banking\Enums\ChequeStatus;
use App\Modules\Banking\Events\ChequeBounced;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\Cheque;
use App\Modules\Banking\Models\ChequeBook;
use App\Modules\Banking\Support\BankBalances;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Support\ContactBalances;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    seedAccounting();

    $this->jod = Currency::query()->where('code', 'JOD')->value('id');
    $this->bank = app(SaveBankAccount::class)->handle(new BankAccount, new BankAccountData(name: 'Arab Bank', type: BankAccountType::Bank, currency_id: $this->jod));
    $this->customer = Contact::factory()->create(['name' => 'Customer']);
    $this->vendor = Contact::factory()->vendor()->create(['name' => 'Vendor']);
});

function ledgerBalance(string $code): string
{
    return Decimal::round(app(AccountBalances::class)->asOf(now())[account($code)->id] ?? '0');
}

function contactBalance(Contact $contact): string
{
    return app(ContactBalances::class)->balance($contact);
}

/**
 * @param  array<string, mixed>  $data
 */
function newCheque(string $direction, array $data = []): Cheque
{
    test()->actingAs(staffMember('Cheque Create'))
        ->post(route('admin.banking.cheques.store'), [
            'direction' => $direction,
            'number' => '100200',
            'contact_id' => $direction === 'incoming' ? test()->customer->id : test()->vendor->id,
            'amount' => '500',
            'currency_id' => test()->jod,
            'issue_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'bank_account_id' => $direction === 'outgoing' ? test()->bank->id : null,
            ...$data,
        ])
        ->assertSessionHasNoErrors();

    return Cheque::query()->latest('id')->firstOrFail();
}

/**
 * @param  array<string, mixed>  $data
 */
function chequeAction(Cheque $cheque, string $action, array $data = []): void
{
    test()->actingAs(staffMember('Cheque Manage'))
        ->post(route('admin.banking.cheques.perform', $cheque), ['action' => $action, 'date' => '2026-06-10', ...$data])
        ->assertSessionHasNoErrors();
}

describe('received cheques', function () {
    test('receiving a cheque settles the customer into cheques in hand', function () {
        $cheque = newCheque('incoming', ['drawee_bank' => 'Housing Bank']);

        expect($cheque->status)->toBe(ChequeStatus::InHand)
            ->and(ledgerBalance('1104'))->toBe('500.0000')
            ->and(contactBalance($this->customer))->toBe('-500.0000')
            ->and($cheque->events)->toHaveCount(1);
    });

    test('a deposited cheque is collected into the bank', function () {
        $cheque = newCheque('incoming');

        chequeAction($cheque, 'deposit', ['bank_account_id' => $this->bank->id]);

        expect($cheque->fresh()->status)->toBe(ChequeStatus::UnderCollection)
            ->and(ledgerBalance('1104'))->toBe('0.0000')
            ->and(ledgerBalance('1105'))->toBe('500.0000');

        chequeAction($cheque, 'collect');

        expect($cheque->fresh()->status)->toBe(ChequeStatus::Collected)
            ->and(ledgerBalance('1105'))->toBe('0.0000')
            ->and(app(BankBalances::class)->balance($this->bank))->toBe('500.0000');
    });

    test('a bounced cheque puts the amount back on the customer, and can be presented again', function () {
        Event::fake([ChequeBounced::class]);
        $cheque = newCheque('incoming');
        chequeAction($cheque, 'deposit', ['bank_account_id' => $this->bank->id]);

        chequeAction($cheque, 'bounce', ['note' => 'Insufficient funds']);

        expect($cheque->fresh()->status)->toBe(ChequeStatus::Bounced)
            ->and(ledgerBalance('1105'))->toBe('0.0000')
            ->and(contactBalance($this->customer))->toBe('0.0000');
        Event::assertDispatched(ChequeBounced::class, fn (ChequeBounced $event): bool => $event->cheque->is($cheque));

        chequeAction($cheque, 'represent');

        expect($cheque->fresh()->status)->toBe(ChequeStatus::InHand)
            ->and(ledgerBalance('1104'))->toBe('500.0000')
            ->and(contactBalance($this->customer))->toBe('-500.0000');
    });

    test('a cheque in hand can be endorsed to a vendor', function () {
        $cheque = newCheque('incoming');

        chequeAction($cheque, 'endorse', ['endorse_to_contact_id' => $this->vendor->id]);

        expect($cheque->fresh())->status->toBe(ChequeStatus::Endorsed)->endorsed_to_contact_id->toBe($this->vendor->id)
            ->and(ledgerBalance('1104'))->toBe('0.0000')
            ->and(contactBalance($this->vendor))->toBe('500.0000');
    });

    test('a cheque returned to the customer puts the amount back on them', function () {
        $cheque = newCheque('incoming');

        chequeAction($cheque, 'return');

        expect($cheque->fresh()->status)->toBe(ChequeStatus::Returned)
            ->and(contactBalance($this->customer))->toBe('0.0000');
    });

    test('a step that does not apply to the status is refused', function () {
        $cheque = newCheque('incoming');
        chequeAction($cheque, 'return');

        $this->actingAs(staffMember('Cheque Manage'))
            ->post(route('admin.banking.cheques.perform', $cheque), ['action' => 'collect', 'date' => '2026-06-10', 'bank_account_id' => $this->bank->id])
            ->assertSessionHasErrors('accounting');

        $this->actingAs(staffMember('Cheque Manage'))
            ->post(route('admin.banking.cheques.perform', $cheque), ['action' => 'clear', 'date' => '2026-06-10'])
            ->assertSessionHasErrors('accounting');
    });

    test('a cheque is deposited only in a bank account of its currency', function () {
        $usdBank = app(SaveBankAccount::class)->handle(new BankAccount, new BankAccountData(
            name: 'USD bank', type: BankAccountType::Bank, currency_id: Currency::query()->where('code', 'USD')->value('id'),
        ));
        $cheque = newCheque('incoming');

        $this->actingAs(staffMember('Cheque Manage'))
            ->post(route('admin.banking.cheques.perform', $cheque), ['action' => 'deposit', 'date' => '2026-06-10', 'bank_account_id' => $usdBank->id])
            ->assertSessionHasErrors('accounting');

        expect($cheque->fresh()->status)->toBe(ChequeStatus::InHand);
    });

    test('the last step can be undone', function () {
        $cheque = newCheque('incoming');
        chequeAction($cheque, 'deposit', ['bank_account_id' => $this->bank->id]);

        $this->actingAs(staffMember('Cheque Manage'))->post(route('admin.banking.cheques.undo', $cheque))->assertRedirect();

        expect($cheque->fresh()->status)->toBe(ChequeStatus::InHand)
            ->and(ledgerBalance('1104'))->toBe('500.0000')
            ->and(ledgerBalance('1105'))->toBe('0.0000');

        $this->actingAs(staffMember('Cheque Manage'))
            ->post(route('admin.banking.cheques.undo', $cheque))
            ->assertSessionHasErrors('accounting');
    });
});

describe('issued cheques', function () {
    test('an issued cheque settles the vendor into cheques payable, and clears from the bank', function () {
        $cheque = newCheque('outgoing');

        expect($cheque->status)->toBe(ChequeStatus::Issued)
            ->and(ledgerBalance('2102'))->toBe('-500.0000')
            ->and(contactBalance($this->vendor))->toBe('500.0000');

        chequeAction($cheque, 'clear');

        expect($cheque->fresh()->status)->toBe(ChequeStatus::Cleared)
            ->and(ledgerBalance('2102'))->toBe('0.0000')
            ->and(app(BankBalances::class)->balance($this->bank))->toBe('-500.0000');
    });

    test('a bounced cheque is owed to the vendor again, then reissued or cancelled', function () {
        $cheque = newCheque('outgoing');

        chequeAction($cheque, 'bounce');

        expect(contactBalance($this->vendor))->toBe('0.0000')->and(ledgerBalance('2102'))->toBe('0.0000');

        chequeAction($cheque, 'reissue');
        chequeAction($cheque, 'cancel');

        expect($cheque->fresh()->status)->toBe(ChequeStatus::Cancelled)
            ->and(contactBalance($this->vendor))->toBe('0.0000');
    });

    test('numbers come from the cheque book and are unique for the account', function () {
        $book = ChequeBook::factory()->create(['bank_account_id' => $this->bank->id, 'first_number' => 5001, 'last_number' => 5002, 'next_number' => 5001]);

        expect(newCheque('outgoing', ['number' => null, 'cheque_book_id' => $book->id])->number)->toBe('5001')
            ->and($book->fresh()->next_number)->toBe(5002);

        $this->actingAs(staffMember('Cheque Create'))
            ->post(route('admin.banking.cheques.store'), [
                'direction' => 'outgoing', 'number' => '5001', 'contact_id' => $this->vendor->id, 'amount' => '1',
                'currency_id' => $this->jod, 'issue_date' => '2026-06-01', 'due_date' => '2026-06-01', 'bank_account_id' => $this->bank->id,
            ])
            ->assertSessionHasErrors('number');
    });
});

describe('register', function () {
    test('the register sums the open cheques and lists the steps available', function () {
        newCheque('incoming', ['amount' => '300']);
        $deposited = newCheque('incoming', ['number' => '2', 'amount' => '200', 'due_date' => '2026-06-16']);
        chequeAction($deposited, 'deposit', ['bank_account_id' => $this->bank->id]);
        newCheque('outgoing', ['amount' => '150']);

        $this->actingAs(staffMember('Cheque View List'))
            ->get(route('admin.banking.cheques.index', ['filter' => ['open' => '1']]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/banking/cheques/Index')
                ->has('cheques.data', 3)
                ->where('cheques.data.0.number', '2')
                ->where('summary.in_hand', '300.0000')
                ->where('summary.under_collection', '200.0000')
                ->where('summary.issued', '150.0000')
                ->where('summary.due_this_week', '200.0000'));

        $this->actingAs(staffMember('Cheque View', 'Cheque Manage'))
            ->get(route('admin.banking.cheques.show', $deposited))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/banking/cheques/Show')
                ->where('cheque.data.actions.0.value', 'collect')
                ->where('cheque.data.actions.1.value', 'bounce')
                ->has('cheque.data.events', 2)
                ->where('canUndo', true));
    });

    test('managing cheques needs its own permission', function () {
        $cheque = newCheque('incoming');

        $this->actingAs(staffMember('Cheque View'))
            ->post(route('admin.banking.cheques.perform', $cheque), ['action' => 'return', 'date' => '2026-06-10'])
            ->assertForbidden();
    });
});

describe('printing', function () {
    test('a cheque and its history print for whoever can see it', function () {
        $cheque = newCheque('incoming');
        chequeAction($cheque, 'deposit', ['bank_account_id' => $this->bank->id]);

        $response = $this->actingAs(staffMember('Cheque View'))
            ->get(route('admin.banking.cheques.pdf', $cheque))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');

        $this->actingAs(staffMember())->get(route('admin.banking.cheques.pdf', $cheque))->assertForbidden();
    });
});
