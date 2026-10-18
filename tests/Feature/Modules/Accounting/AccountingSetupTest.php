<?php

use App\Modules\Accounting\Enums\FiscalYearStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Settings\AccountingSettings;
use App\Modules\Accounting\Support\AccountBalances;

beforeEach(function () {
    seedAccounting();
});

describe('currencies', function () {
    test('exchange rates are saved and looked up by date', function () {
        $usd = Currency::query()->where('code', 'USD')->sole();

        $this->actingAs(staffMember('Currency Update'))
            ->post(route('admin.accounting.currencies.rates.store', $usd), ['effective_date' => now()->subDays(5)->toDateString(), 'rate' => '0.709'])
            ->assertRedirect();

        $this->actingAs(staffMember())
            ->getJson(route('admin.accounting.exchange-rate', ['currency_id' => $usd->id, 'date' => now()->toDateString()]))
            ->assertOk()
            ->assertJson(['rate' => '0.70900000', 'is_base' => false]);
    });

    test('a missing rate is reported as a 422 for the form', function () {
        $usd = Currency::query()->where('code', 'USD')->sole();

        $this->actingAs(staffMember())
            ->getJson(route('admin.accounting.exchange-rate', ['currency_id' => $usd->id, 'date' => now()->toDateString()]))
            ->assertUnprocessable();
    });

    test('the base currency can never be deleted', function () {
        $this->actingAs(staffMember('Currency Delete'))
            ->delete(route('admin.accounting.currencies.destroy', Currency::query()->where('code', 'JOD')->sole()))
            ->assertForbidden();
    });
});

describe('taxes', function () {
    test('a tax with a rate needs the accounts of the sides it applies to', function () {
        $this->actingAs(staffMember('Tax Create'))
            ->post(route('admin.accounting.taxes.store'), ['name' => ['en' => 'VAT 5%'], 'rate' => '5', 'scope' => 'sales'])
            ->assertSessionHasErrors('sales_account_id')
            ->assertSessionDoesntHaveErrors('purchase_account_id');
    });
});

describe('fiscal years', function () {
    test('fiscal years cannot overlap', function () {
        $this->actingAs(staffMember('Fiscal Year Create'))
            ->post(route('admin.accounting.fiscal-years.store'), [
                'name' => 'Overlap',
                'start_date' => now()->startOfYear()->addMonths(6)->toDateString(),
                'end_date' => now()->endOfYear()->addMonths(6)->toDateString(),
            ])
            ->assertSessionHasErrors('end_date');
    });

    test('closing a year moves the profit into retained earnings and locks the year', function () {
        $year = FiscalYear::query()->sole();
        JournalEntryBuilder::make(now())->debit(account('110101'), 1000)->credit(account('4101'), 1000)->post();
        JournalEntryBuilder::make(now())->debit(account('5203'), 300)->credit(account('110101'), 300)->post();

        $this->actingAs(staffMember('Fiscal Year Close'))
            ->post(route('admin.accounting.fiscal-years.close', $year))
            ->assertRedirect();

        $balances = app(AccountBalances::class)->asOf($year->end_date);

        expect($year->fresh()->status)->toBe(FiscalYearStatus::Closed)
            ->and($balances[account('4101')->id])->toBe('0.0000')
            ->and($balances[account('5203')->id])->toBe('0.0000')
            ->and($balances[account('3102')->id])->toBe('-700.0000');

        expect(fn () => JournalEntryBuilder::make(now())->debit(account('5203'), 1)->credit(account('110101'), 1)->post())
            ->toThrow(AccountingException::class, 'closed');
    });

    test('reopening reverses the closing entry', function () {
        $year = FiscalYear::query()->sole();
        JournalEntryBuilder::make(now())->debit(account('110101'), 1000)->credit(account('4101'), 1000)->post();
        $staffMember = staffMember('Fiscal Year Close');

        $this->actingAs($staffMember)->post(route('admin.accounting.fiscal-years.close', $year));
        $this->actingAs($staffMember)->post(route('admin.accounting.fiscal-years.reopen', $year))->assertRedirect();

        $balances = app(AccountBalances::class)->asOf($year->end_date);

        expect($year->fresh()->status)->toBe(FiscalYearStatus::Open)
            ->and($balances[account('4101')->id])->toBe('-1000.0000')
            ->and($balances[account('3102')->id])->toBe('0.0000');
    });
});

describe('settings', function () {
    test('default accounts, lock date and prefixes are saved', function () {
        $this->actingAs(staffMember('Accounting Setting View', 'Accounting Setting Update'))
            ->put(route('admin.accounting.settings.update'), [
                'base_currency' => 'JOD',
                'lock_date' => now()->subMonth()->toDateString(),
                'default_accounts' => ['receivable' => account('1103')->id],
                'number_prefixes' => ['sales_invoice' => 'inv'],
            ])
            ->assertRedirect();

        $settings = app(AccountingSettings::class)->refresh();

        expect($settings->lock_date)->toBe(now()->subMonth()->toDateString())
            ->and($settings->default_accounts)->toBe(['receivable' => account('1103')->id])
            ->and($settings->number_prefixes)->toBe(['sales_invoice' => 'INV']);
    });

    test('the settings form leaves the base currency alone', function () {
        JournalEntryBuilder::make(now())->debit(account('110101'), 10)->credit(account('3101'), 10)->post();

        $this->actingAs(staffMember('Accounting Setting Update'))
            ->put(route('admin.accounting.settings.update'), ['base_currency' => 'USD'])
            ->assertSessionHasNoErrors();

        expect(app(AccountingSettings::class)->refresh()->base_currency)->toBe('JOD')
            ->and(JournalEntry::query()->count())->toBe(1);
    });
});
