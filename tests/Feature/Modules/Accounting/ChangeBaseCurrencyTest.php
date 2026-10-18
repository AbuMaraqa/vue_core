<?php

use App\Modules\Accounting\Actions\Currencies\ChangeBaseCurrency;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\ExchangeRate;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Settings\AccountingSettings;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Banking\Actions\BankAccounts\SaveBankAccount;
use App\Modules\Banking\Data\BankAccountData;
use App\Modules\Banking\Enums\BankAccountType;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Support\BankBalances;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Support\StockLedger;
use App\Modules\Payroll\Models\Employee;

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    seedAccounting();

    $this->jod = currencyByCode('JOD');
    $this->ils = currencyByCode('ILS');
    $this->usd = currencyByCode('USD');

    ExchangeRate::query()->create(['currency_id' => $this->usd->id, 'effective_date' => '2026-01-01', 'rate' => '0.709']);
    ExchangeRate::query()->create(['currency_id' => $this->ils->id, 'effective_date' => '2026-01-01', 'rate' => '0.2']);
});

function currencyByCode(string $code): Currency
{
    return Currency::query()->where('code', $code)->sole();
}

/**
 * Move the books from JOD to ILS at 1 JOD = 5 ILS.
 */
function changeToShekels(): void
{
    app(ChangeBaseCurrency::class)->handle(currencyByCode('ILS'), '5');
}

function rebasedBalance(string $code): string
{
    return Decimal::round(app(AccountBalances::class)->asOf(now())[account($code)->id] ?? '0');
}

function cashBox(string $name, string $currency): BankAccount
{
    return app(SaveBankAccount::class)->handle(new BankAccount, new BankAccountData(
        name: $name,
        type: BankAccountType::Cash,
        currency_id: currencyByCode($currency)->id,
    ));
}

test('every entry is worked out again in the new base currency', function () {
    JournalEntryBuilder::make(now())->debit(account('110101'), 100)->credit(account('3101'), 100)->post();
    JournalEntryBuilder::make(now())->currency($this->usd, '0.709')->debit(account('1103'), 10)->credit(account('4101'), 10)->post();
    JournalEntryBuilder::make(now())->currency($this->ils, '0.2')->debit(account('1103'), 50)->credit(account('4101'), 50)->post();

    changeToShekels();

    expect(app(AccountingSettings::class)->refresh()->base_currency)->toBe('ILS')
        ->and(rebasedBalance('110101'))->toBe('500.0000')
        ->and(rebasedBalance('3101'))->toBe('-500.0000')
        ->and(rebasedBalance('1103'))->toBe('85.4500')
        ->and(rebasedBalance('4101'))->toBe('-85.4500')
        ->and(JournalEntry::query()->where('currency_id', $this->ils->id)->sole()->exchange_rate)->toBe('1.00000000')
        ->and(JournalEntry::query()->where('currency_id', $this->jod->id)->sole()->exchange_rate)->toBe('5.00000000');
});

test('the old base currency gets the rate and the others are re-expressed', function () {
    changeToShekels();

    expect($this->ils->exchangeRates()->count())->toBe(0)
        ->and($this->jod->exchangeRates()->sole()->rate)->toBe('5.00000000')
        ->and($this->usd->exchangeRates()->sole()->rate)->toBe('3.54500000');
});

test('stock, prices and salaries are converted', function () {
    $item = Item::factory()->create(['purchase_price' => '4', 'sale_price' => '6']);
    $warehouse = Warehouse::factory()->create();
    $employee = Employee::factory()->create(['basic_salary' => '600']);
    app(StockLedger::class)->receive($item, $warehouse, '3', '1.5', now(), StockMovementType::Purchase);

    changeToShekels();

    $level = StockLevel::query()->sole();

    expect($item->fresh())->purchase_price->toBe('20.0000')->sale_price->toBe('30.0000')
        ->and($employee->fresh()->basic_salary)->toBe('3000.0000')
        ->and($level->value)->toBe('22.5000')
        ->and($level->average_cost)->toBe('7.500000');
});

test('a cash box in the old base currency keeps its balance in that currency', function () {
    $jodBox = cashBox('JOD box', 'JOD');
    $ilsBox = cashBox('ILS box', 'ILS');
    JournalEntryBuilder::make(now())->debit($jodBox->account, 100)->credit(account('3101'), 100)->post();
    JournalEntryBuilder::make(now())->currency($this->ils, '0.2')->debit($ilsBox->account, 50)->credit(account('3101'), 50)->post();

    changeToShekels();

    $balances = app(BankBalances::class)->of(BankAccount::query()->get());

    expect($jodBox->account->fresh()->currency_id)->toBe($this->jod->id)
        ->and($ilsBox->account->fresh()->currency_id)->toBeNull()
        ->and($balances[$jodBox->id])->toBe(['balance' => '100.0000', 'base_balance' => '500.0000'])
        ->and($balances[$ilsBox->id])->toBe(['balance' => '50.0000', 'base_balance' => '50.0000']);
});

test('a cash box with transfers in other currencies stops the change', function () {
    $jodBox = cashBox('JOD box', 'JOD');
    JournalEntryBuilder::make(now())->currency($this->usd, '0.709')->debit($jodBox->account, 10)->credit(account('3101'), 10)->post();

    expect(fn () => changeToShekels())->toThrow(AccountingException::class, 'JOD box');

    expect(app(AccountingSettings::class)->refresh()->base_currency)->toBe('JOD')
        ->and(rebasedBalance('3101'))->toBe('-7.0900');
});

test('the settings page changes the base currency', function () {
    $this->actingAs(staffMember('Accounting Setting Update'))
        ->post(route('admin.accounting.settings.base-currency'), ['currency_id' => $this->ils->id, 'rate' => '5'])
        ->assertRedirect(route('admin.accounting.settings.edit'));

    expect(app(AccountingSettings::class)->refresh()->base_currency)->toBe('ILS');
});

test('changing the base currency needs a new currency and a positive rate', function () {
    $this->actingAs(staffMember('Accounting Setting Update'))
        ->post(route('admin.accounting.settings.base-currency'), ['currency_id' => $this->jod->id, 'rate' => '0'])
        ->assertSessionHasErrors(['currency_id', 'rate']);
});

test('changing the base currency needs the settings update permission', function () {
    $this->actingAs(staffMember('Accounting Setting View'))
        ->post(route('admin.accounting.settings.base-currency'), ['currency_id' => $this->ils->id, 'rate' => '5'])
        ->assertForbidden();

    expect(app(AccountingSettings::class)->refresh()->base_currency)->toBe('JOD');
});
