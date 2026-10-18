<?php

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Banking\Actions\BankAccounts\SaveBankAccount;
use App\Modules\Banking\Data\BankAccountData;
use App\Modules\Banking\Enums\BankAccountType;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\BankReconciliation;
use App\Modules\Banking\Models\BankTransfer;
use App\Modules\Banking\Models\BankVoucher;
use App\Modules\Banking\Support\BankBalances;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    seedAccounting();
});

/**
 * A bank or cash account created the way the screen does it.
 */
function bankAccount(string $name, string $currency = 'JOD', BankAccountType $type = BankAccountType::Bank): BankAccount
{
    return app(SaveBankAccount::class)->handle(new BankAccount, new BankAccountData(
        name: $name,
        type: $type,
        currency_id: Currency::query()->where('code', $currency)->value('id'),
    ));
}

function glBalance(string $code): string
{
    return Decimal::round(app(AccountBalances::class)->asOf(now())[account($code)->id] ?? '0');
}

/**
 * @param  list<array<string, mixed>>  $lines
 * @param  array<string, mixed>  $extra
 */
function voucher(BankAccount $bank, string $direction, array $lines, array $extra = []): BankVoucher
{
    test()->actingAs(staffMember('Bank Voucher Create'))
        ->post(route('admin.banking.vouchers.store'), [
            'direction' => $direction,
            'date' => '2026-06-10',
            'bank_account_id' => $bank->id,
            'lines' => $lines,
            ...$extra,
        ])
        ->assertSessionHasNoErrors();

    return BankVoucher::query()->latest('id')->firstOrFail();
}

/**
 * @param  array<string, mixed>  $data
 */
function transfer(array $data): void
{
    test()->actingAs(staffMember('Bank Transfer Create'))
        ->post(route('admin.banking.transfers.store'), ['date' => '2026-06-11', ...$data])
        ->assertSessionHasNoErrors();
}

/**
 * The seeded 16% tax.
 */
function vat(): int
{
    return (int) Tax::query()->where('rate', 16)->value('id');
}

describe('accounts', function () {
    test('a new bank account gets its own account under the banks group', function () {
        $this->actingAs(staffMember('Bank Account Create'))
            ->post(route('admin.banking.accounts.store'), [
                'name' => 'Arab Bank',
                'type' => 'bank',
                'currency_id' => Currency::query()->where('code', 'USD')->value('id'),
                'iban' => 'JO94CBJO0010000000000131000302',
            ])
            ->assertSessionHasNoErrors();

        $bank = BankAccount::query()->with('account')->sole();

        expect($bank->account)
            ->code->toBe('110201')
            ->parent_id->toBe(account('1102')->id)
            ->currency_id->toBe($bank->currency_id);
    });

    test('a base-currency cash box accepts any currency and an existing account can be linked', function () {
        $this->actingAs(staffMember('Bank Account Create'))
            ->post(route('admin.banking.accounts.store'), [
                'name' => 'Main cash',
                'type' => 'cash',
                'currency_id' => Currency::query()->where('code', 'JOD')->value('id'),
                'account_id' => account('110101')->id,
            ])
            ->assertSessionHasNoErrors();

        expect(BankAccount::query()->sole())
            ->account_id->toBe(account('110101')->id)
            ->account->currency_id->toBeNull();

        $this->actingAs(staffMember('Bank Account Create'))
            ->post(route('admin.banking.accounts.store'), [
                'name' => 'Second',
                'type' => 'cash',
                'currency_id' => Currency::query()->where('code', 'JOD')->value('id'),
                'account_id' => account('110101')->id,
            ])
            ->assertSessionHasErrors('account_id');
    });

    test('the treasury lists every account with its balance', function () {
        $bank = bankAccount('Arab Bank');
        voucher($bank, 'in', [['account_id' => account('3101')->id, 'amount' => '5000']]);

        $this->actingAs(staffMember('Bank Account View List'))
            ->get(route('admin.banking.accounts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/banking/accounts/Index')
                ->where('bankAccounts.0.balance', '5000.0000')
                ->where('totals.bank', '5000.0000')
                ->where('bankAccounts.0.can.delete', false));
    });

    test('only an account money never moved through can be deleted', function () {
        $used = bankAccount('Arab Bank');
        $unused = bankAccount('Unused Bank');
        voucher($used, 'in', [['account_id' => account('3101')->id, 'amount' => '5000']]);
        $user = staffMember('Bank Account View List', 'Bank Account Delete');

        $this->actingAs($user)
            ->get(route('admin.banking.accounts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('bankAccounts.0.name', 'Arab Bank')
                ->where('bankAccounts.0.can.delete', false)
                ->where('bankAccounts.1.name', 'Unused Bank')
                ->where('bankAccounts.1.can.delete', true));

        $this->actingAs($user)
            ->delete(route('admin.banking.accounts.destroy', $used))
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('admin.banking.accounts.destroy', $unused))
            ->assertRedirect(route('admin.banking.accounts.index'));

        expect(BankAccount::query()->pluck('name')->all())->toBe(['Arab Bank']);
    });
});

describe('transfers', function () {
    beforeEach(function () {
        $this->jod = bankAccount('JOD bank');
        $this->cash = bankAccount('Cash', type: BankAccountType::Cash);
        $this->usd = bankAccount('USD bank', 'USD');
        voucher($this->jod, 'in', [['account_id' => account('3101')->id, 'amount' => '10000']]);
    });

    test('money moves between accounts of one currency, with the fees charged', function () {
        transfer(['from_bank_account_id' => $this->jod->id, 'to_bank_account_id' => $this->cash->id, 'amount' => '1500', 'fees' => '2.5']);

        $balances = app(BankBalances::class);

        expect($balances->balance($this->jod))->toBe('8497.5000')
            ->and($balances->balance($this->cash))->toBe('1500.0000')
            ->and(glBalance('5206'))->toBe('2.5000');
    });

    test('a transfer to a foreign currency keeps both amounts exactly', function () {
        transfer(['from_bank_account_id' => $this->jod->id, 'to_bank_account_id' => $this->usd->id, 'amount' => '709', 'to_amount' => '1000']);

        $balances = app(BankBalances::class)->of(collect([$this->jod, $this->usd]));

        expect($balances[$this->usd->id])->balance->toBe('1000.0000')->base_balance->toBe('709.0000')
            ->and($balances[$this->jod->id]['balance'])->toBe('9291.0000');
    });

    test('two foreign currencies go through the base currency', function () {
        $eur = bankAccount('EUR bank', 'EUR');

        $this->actingAs(staffMember('Bank Transfer Create'))
            ->post(route('admin.banking.transfers.store'), [
                'date' => '2026-06-11', 'from_bank_account_id' => $this->usd->id, 'to_bank_account_id' => $eur->id, 'amount' => '10', 'to_amount' => '9',
            ])
            ->assertSessionHasErrors('accounting');
    });

    test('cancelling reverses the transfer and its fees', function () {
        transfer(['from_bank_account_id' => $this->jod->id, 'to_bank_account_id' => $this->cash->id, 'amount' => '1500', 'fees' => '2.5']);
        $transfer = BankTransfer::query()->sole();

        $this->actingAs(staffMember('Bank Transfer Cancel'))
            ->post(route('admin.banking.transfers.cancel', $transfer))
            ->assertRedirect();

        expect(app(BankBalances::class)->balance($this->jod))->toBe('10000.0000')
            ->and(glBalance('5206'))->toBe('0.0000');
    });
});

describe('vouchers', function () {
    beforeEach(function () {
        $this->cash = bankAccount('Cash', type: BankAccountType::Cash);
    });

    test('a payment voucher posts each line, the recoverable tax and the total', function () {
        $voucher = voucher($this->cash, 'out', [
            ['account_id' => account('5203')->id, 'amount' => '100', 'tax_id' => vat(), 'description' => 'June rent'],
            ['account_id' => account('5206')->id, 'amount' => '5'],
        ], ['payee' => 'Landlord']);

        expect($voucher)
            ->number->toStartWith('EXP-2026-')
            ->subtotal->toBe('105.0000')
            ->tax_total->toBe('16.0000')
            ->total->toBe('121.0000')
            ->and(glBalance('5203'))->toBe('100.0000')
            ->and(glBalance('1107'))->toBe('16.0000')
            ->and(app(BankBalances::class)->balance($this->cash))->toBe('-121.0000');
    });

    test('the simple expense form offers only expense accounts and posts one line', function () {
        $this->actingAs(staffMember('Bank Voucher Create'))
            ->get(route('admin.banking.vouchers.create', ['direction' => 'out', 'kind' => 'expense']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/banking/vouchers/Expense')
                ->where('expenseAccounts', fn ($accounts): bool => collect($accounts)->pluck('value')->contains(account('5203')->id)
                    && ! collect($accounts)->pluck('value')->contains(account('4101')->id)
                    && ! collect($accounts)->pluck('value')->contains(account('1103')->id)),
            );

        $voucher = voucher($this->cash, 'out', [
            ['account_id' => account('5203')->id, 'amount' => '250', 'description' => 'June rent', 'tax_id' => null, 'cost_center_id' => null],
        ], ['payee' => 'Landlord', 'description' => 'June rent', 'exchange_rate' => null]);

        expect($voucher->total)->toBe('250.0000')
            ->and(glBalance('5203'))->toBe('250.0000')
            ->and(app(BankBalances::class)->balance($this->cash))->toBe('-250.0000');
    });

    test('a receipt voucher credits the lines and the tax owed', function () {
        $voucher = voucher($this->cash, 'in', [['account_id' => account('4101')->id, 'amount' => '200', 'tax_id' => vat()]]);

        expect($voucher->number)->toStartWith('DEP-2026-')
            ->and(glBalance('4101'))->toBe('-200.0000')
            ->and(glBalance('2103'))->toBe('-32.0000')
            ->and(app(BankBalances::class)->balance($this->cash))->toBe('232.0000');
    });

    test('a voucher cannot post to its own bank account', function () {
        $this->actingAs(staffMember('Bank Voucher Create'))
            ->post(route('admin.banking.vouchers.store'), [
                'direction' => 'out', 'date' => '2026-06-10', 'bank_account_id' => $this->cash->id,
                'lines' => [['account_id' => $this->cash->account_id, 'amount' => '10']],
            ])
            ->assertSessionHasErrors('lines.0.account_id');
    });

    test('cancelling reverses the voucher once', function () {
        $voucher = voucher($this->cash, 'out', [['account_id' => account('5203')->id, 'amount' => '100']]);

        $this->actingAs(staffMember('Bank Voucher Cancel'))->post(route('admin.banking.vouchers.cancel', $voucher))->assertRedirect();

        expect($voucher->fresh()->status)->toBe(DocumentStatus::Cancelled)
            ->and(app(BankBalances::class)->balance($this->cash))->toBe('0.0000');

        $this->actingAs(staffMember('Bank Voucher Cancel'))->post(route('admin.banking.vouchers.cancel', $voucher))->assertForbidden();
    });
});

describe('reconciliation', function () {
    beforeEach(function () {
        $this->bank = bankAccount('Arab Bank');
        voucher($this->bank, 'in', [['account_id' => account('3101')->id, 'amount' => '1000']]);
        voucher($this->bank, 'out', [['account_id' => account('5203')->id, 'amount' => '300']]);
        voucher($this->bank, 'out', [['account_id' => account('5206')->id, 'amount' => '20']]);
        $this->lineIds = JournalEntry::query()->with('lines')->get()
            ->flatMap->lines->where('account_id', $this->bank->account_id)->pluck('id')->values()->all();
    });

    test('the ticked lines must add up to the statement balance', function () {
        $this->actingAs(staffMember('Bank Account Reconcile'))
            ->post(route('admin.banking.accounts.reconcile.store', $this->bank), [
                'statement_date' => '2026-06-30', 'statement_balance' => '700', 'line_ids' => [$this->lineIds[0]],
            ])
            ->assertSessionHasErrors('accounting');

        $this->actingAs(staffMember('Bank Account Reconcile'))
            ->post(route('admin.banking.accounts.reconcile.store', $this->bank), [
                'statement_date' => '2026-06-30', 'statement_balance' => '700', 'line_ids' => [$this->lineIds[0], $this->lineIds[1]],
            ])
            ->assertRedirect(route('admin.banking.accounts.show', $this->bank));

        $this->actingAs(staffMember('Bank Account Reconcile'))
            ->get(route('admin.banking.accounts.reconcile', [$this->bank, 'statement_date' => '2026-06-30']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/banking/reconciliations/Create')
                ->where('reconciledBalance', '700.0000')
                ->has('lines', 1));
    });

    test('a reconciled entry cannot be reversed until the reconciliation is undone', function () {
        $this->actingAs(staffMember('Bank Account Reconcile'))
            ->post(route('admin.banking.accounts.reconcile.store', $this->bank), [
                'statement_date' => '2026-06-30', 'statement_balance' => '1000', 'line_ids' => [$this->lineIds[0]],
            ]);

        $entry = JournalEntry::query()->orderBy('id')->firstOrFail();

        expect(fn () => app(Ledger::class)->reverse($entry))->toThrow(AccountingException::class, 'reconciled');

        $this->actingAs(staffMember('Bank Account Reconcile'))
            ->delete(route('admin.banking.accounts.reconciliations.destroy', [$this->bank, BankReconciliation::query()->sole()]))
            ->assertRedirect();

        expect(BankReconciliation::query()->exists())->toBeFalse()
            ->and(app(Ledger::class)->reverse($entry->fresh())->status->value)->toBe('posted');
    });

    test('the register runs from the opening balance', function () {
        $this->actingAs(staffMember('Bank Account View'))
            ->get(route('admin.banking.accounts.show', [$this->bank, 'from' => '2026-06-01', 'to' => '2026-06-30']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/banking/accounts/Show')
                ->where('register.opening', '0.0000')
                ->where('register.total_in', '1000.0000')
                ->where('register.total_out', '320.0000')
                ->where('register.closing', '680.0000')
                ->where('register.lines.2.balance', '680.0000'));
    });
});

describe('printing', function () {
    beforeEach(function () {
        $this->bank = bankAccount('Arab Bank');
        $this->cash = bankAccount('Cash', type: BankAccountType::Cash);
        $this->voucher = voucher($this->bank, 'in', [['account_id' => account('4101')->id, 'amount' => '1000', 'tax_id' => vat()]], ['payee' => 'Owner']);
        transfer(['from_bank_account_id' => $this->bank->id, 'to_bank_account_id' => $this->cash->id, 'amount' => '150', 'fees' => '2.5']);
    });

    test('a voucher prints for whoever can see it', function () {
        $response = $this->actingAs(staffMember('Bank Voucher View'))
            ->get(route('admin.banking.vouchers.pdf', $this->voucher))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');

        $this->actingAs(staffMember())->get(route('admin.banking.vouchers.pdf', $this->voucher))->assertForbidden();
    });

    test('a transfer prints for whoever can see it', function () {
        $transfer = BankTransfer::query()->sole();

        $response = $this->actingAs(staffMember('Bank Transfer View'))
            ->get(route('admin.banking.transfers.pdf', $transfer))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');

        $this->actingAs(staffMember())->get(route('admin.banking.transfers.pdf', $transfer))->assertForbidden();
    });

    test('the statement of an account prints for its period', function () {
        $response = $this->actingAs(staffMember('Bank Account View'))
            ->get(route('admin.banking.accounts.pdf', [$this->bank, 'from' => '2026-06-01', 'to' => '2026-06-30']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');

        $this->actingAs(staffMember())->get(route('admin.banking.accounts.pdf', $this->bank))->assertForbidden();
    });
});
