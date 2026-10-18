<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedAccounting();
});

test('staff members without the permission are forbidden', function () {
    $this->actingAs(staffMember())->get(route('admin.accounting.accounts.index'))->assertForbidden();
});

test('the tree lists accounts in order with balances rolled up to their groups', function () {
    JournalEntryBuilder::make(now())->debit(account('110101'), '250.500')->credit(account('3101'), '250.500')->post();

    $this->actingAs(staffMember('Account View List'))
        ->get(route('admin.accounting.accounts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/accounting/accounts/Index')
            ->where('accounts.0.code', '1')
            ->where('accounts.0.depth', 0)
            ->where('accounts.0.balance', '250.5000')
            ->where('accounts.1.code', '11')
            ->where('accounts.1.depth', 1)
            ->where('accounts.2.code', '1101')
            ->where('accounts.3.code', '110101')
            ->where('accounts.3.balance', '250.5000')
            ->where('baseCurrency.code', 'JOD'),
        );
});

test('an account is created under a parent of the same type', function () {
    $this->actingAs(staffMember('Account Create'))
        ->post(route('admin.accounting.accounts.store'), [
            'parent_id' => account('52')->id,
            'code' => '5213',
            'name' => ['ar' => 'تأمين', 'en' => 'Insurance'],
            'subtype' => 'operating_expense',
        ])
        ->assertRedirect(route('admin.accounting.accounts.index'));

    expect(account('5213'))
        ->type->value->toBe('expense')
        ->is_group->toBeFalse()
        ->and(account('5213')->translate('name', 'ar'))->toBe('تأمين');
});

test('the account rules are validated', function (array $input, string $field) {
    $this->actingAs(staffMember('Account Create'))
        ->post(route('admin.accounting.accounts.store'), [
            'code' => '9999',
            'name' => ['en' => 'Something'],
            'subtype' => 'operating_expense',
            ...$input,
        ])
        ->assertSessionHasErrors($field);
})->with([
    'type differs from parent' => [fn () => ['parent_id' => account('11')->id, 'subtype' => 'operating_revenue'], 'subtype'],
    'parent is not a group' => [fn () => ['parent_id' => account('110101')->id], 'parent_id'],
    'duplicate code' => [['code' => '1101'], 'code'],
    'no name in any language' => [['name' => ['ar' => '', 'en' => '']], 'name.ar'],
]);

test('an account with postings cannot become a group account', function () {
    JournalEntryBuilder::make(now())->debit(account('5203'), 10)->credit(account('110101'), 10)->post();

    $this->actingAs(staffMember('Account Update'))
        ->put(route('admin.accounting.accounts.update', account('5203')), [
            'parent_id' => account('52')->id,
            'code' => '5203',
            'name' => ['en' => 'Rent'],
            'subtype' => 'operating_expense',
            'is_group' => true,
        ])
        ->assertSessionHasErrors('is_group');
});

test('accounts with postings or an automatic role cannot be deleted', function () {
    JournalEntryBuilder::make(now())->debit(account('5203'), 10)->credit(account('110101'), 10)->post();
    $staffMember = staffMember('Account Delete');

    $this->actingAs($staffMember)->delete(route('admin.accounting.accounts.destroy', account('5203')))->assertForbidden();
    $this->actingAs($staffMember)->delete(route('admin.accounting.accounts.destroy', account('1103')))->assertForbidden();
    $this->actingAs($staffMember)->delete(route('admin.accounting.accounts.destroy', account('5208')))->assertRedirect();

    expect(Account::query()->where('code', '5208')->exists())->toBeFalse();
});
