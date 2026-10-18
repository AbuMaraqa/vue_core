<?php

use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Support\AccountBalances;

beforeEach(function () {
    seedAccounting();
});

describe('budgets', function () {
    test('a budget is saved with its monthly amounts', function () {
        $this->actingAs(staffMember('Budget Create'))
            ->post(route('admin.accounting.budgets.store'), [
                'name' => 'Operating budget',
                'fiscal_year_id' => FiscalYear::query()->sole()->id,
                'lines' => [
                    ['account_id' => account('5203')->id, 'months' => array_fill(0, 12, '400')],
                    ['account_id' => account('4101')->id, 'months' => ['1000', '', '', '', '', '', '', '', '', '', '', '2000']],
                ],
            ])
            ->assertRedirect(route('admin.accounting.budgets.index'));

        $budget = Budget::query()->sole();

        expect($budget->lines)->toHaveCount(14)
            ->and((string) $budget->lines()->sum('amount'))->toBe('7800');
    });

    test('only revenue and expense accounts can be budgeted', function () {
        $this->actingAs(staffMember('Budget Create'))
            ->post(route('admin.accounting.budgets.store'), [
                'name' => 'Wrong',
                'fiscal_year_id' => FiscalYear::query()->sole()->id,
                'lines' => [['account_id' => account('110101')->id, 'months' => array_fill(0, 12, '1')]],
            ])
            ->assertSessionHasErrors('lines.0.account_id');
    });
});

describe('opening balances', function () {
    test('balances are posted and the difference goes to opening balance equity', function () {
        $this->actingAs(staffMember('Journal Entry Create', 'Journal Entry Post'))
            ->post(route('admin.accounting.opening-balances.store'), [
                'date' => now()->startOfYear()->toDateString(),
                'lines' => [
                    ['account_id' => account('110101')->id, 'debit' => '5000'],
                    ['account_id' => account('1203')->id, 'debit' => '12000'],
                    ['account_id' => account('2201')->id, 'credit' => '7000'],
                ],
            ])
            ->assertRedirect();

        $entry = JournalEntry::query()->sole();
        $balances = app(AccountBalances::class)->asOf(now());

        expect($entry->status)->toBe(JournalEntryStatus::Posted)
            ->and($balances[account('3103')->id])->toBe('-10000.0000')
            ->and($balances[account('1203')->id])->toBe('12000.0000');
    });

    test('opening balances need the permissions to create and post entries', function () {
        $this->actingAs(staffMember('Journal Entry Create'))
            ->get(route('admin.accounting.opening-balances.create'))
            ->assertForbidden();
    });
});
