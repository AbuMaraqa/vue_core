<?php

use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Settings\AccountingSettings;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedAccounting();
    $this->jod = Currency::query()->where('code', 'JOD')->sole();
});

/**
 * @return array<string, mixed>
 */
function entryInput(array $overrides = []): array
{
    return [
        'date' => now()->toDateString(),
        'description' => 'Office rent',
        'currency_id' => Currency::query()->where('code', 'JOD')->value('id'),
        'exchange_rate' => '1',
        'lines' => [
            ['account_id' => account('5203')->id, 'debit' => '400', 'credit' => ''],
            ['account_id' => account('110101')->id, 'debit' => '', 'credit' => '400'],
        ],
        ...$overrides,
    ];
}

test('a manual entry is saved as a draft, then posted', function () {
    $staffMember = staffMember('Journal Entry View List', 'Journal Entry Create', 'Journal Entry Post');

    $this->actingAs($staffMember)->post(route('admin.accounting.journal-entries.store'), entryInput())->assertRedirect();

    $entry = JournalEntry::query()->sole();

    expect($entry->status)->toBe(JournalEntryStatus::Draft)
        ->and($entry->number)->toBeNull()
        ->and($entry->lines)->toHaveCount(2);

    $this->actingAs($staffMember)
        ->post(route('admin.accounting.journal-entries.post', $entry))
        ->assertRedirect(route('admin.accounting.journal-entries.show', $entry));

    expect($entry->fresh())->status->toBe(JournalEntryStatus::Posted)->number->not->toBeNull();
});

test('an unbalanced entry or a line with both sides is refused', function (array $lines, string $error) {
    $this->actingAs(staffMember('Journal Entry Create'))
        ->post(route('admin.accounting.journal-entries.store'), entryInput(['lines' => $lines]))
        ->assertSessionHasErrors($error);
})->with([
    'unbalanced' => [fn () => [
        ['account_id' => account('5203')->id, 'debit' => '400'],
        ['account_id' => account('110101')->id, 'credit' => '300'],
    ], 'lines'],
    'both sides' => [fn () => [
        ['account_id' => account('5203')->id, 'debit' => '400', 'credit' => '400'],
        ['account_id' => account('110101')->id, 'credit' => '0'],
    ], 'lines.0.debit'],
    'group account' => [fn () => [
        ['account_id' => account('52')->id, 'debit' => '400'],
        ['account_id' => account('110101')->id, 'credit' => '400'],
    ], 'lines.0.account_id'],
]);

test('posting is refused with a message when the books are locked', function () {
    $settings = app(AccountingSettings::class);
    $settings->lock_date = now()->toDateString();
    $settings->save();

    $entry = JournalEntryBuilder::make(now())->debit(account('5203'), 10)->credit(account('110101'), 10)->save();

    $this->actingAs(staffMember('Journal Entry Post'))
        ->from(route('admin.accounting.journal-entries.show', $entry))
        ->post(route('admin.accounting.journal-entries.post', $entry))
        ->assertRedirect(route('admin.accounting.journal-entries.show', $entry))
        ->assertSessionHasErrors('accounting')
        ->assertInertiaFlash('toast.type', 'error');

    expect($entry->fresh()->status)->toBe(JournalEntryStatus::Draft);
});

test('posted entries cannot be edited or deleted, only reversed', function () {
    $entry = JournalEntryBuilder::make(now())->debit(account('5203'), 10)->credit(account('110101'), 10)->post();
    $staffMember = staffMember('Journal Entry Update', 'Journal Entry Delete', 'Journal Entry Reverse');

    $this->actingAs($staffMember)->put(route('admin.accounting.journal-entries.update', $entry), entryInput())->assertForbidden();
    $this->actingAs($staffMember)->delete(route('admin.accounting.journal-entries.destroy', $entry))->assertForbidden();

    $this->actingAs($staffMember)
        ->post(route('admin.accounting.journal-entries.reverse', $entry), ['date' => now()->toDateString()])
        ->assertRedirect();

    expect($entry->fresh()->status)->toBe(JournalEntryStatus::Reversed);
});

test('entries written by a document cannot be reversed by hand', function () {
    $year = FiscalYear::query()->sole();
    $entry = JournalEntryBuilder::make(now())->source($year)->debit(account('5203'), 10)->credit(account('110101'), 10)->post();

    $this->actingAs(staffMember('Journal Entry Reverse'))
        ->post(route('admin.accounting.journal-entries.reverse', $entry), ['date' => now()->toDateString()])
        ->assertForbidden();
});

test('the list can be filtered by status and searched', function () {
    JournalEntryBuilder::make(now(), 'Office rent')->debit(account('5203'), 10)->credit(account('110101'), 10)->post();
    JournalEntryBuilder::make(now(), 'Electricity')->debit(account('5204'), 10)->credit(account('110101'), 10)->save();

    $this->actingAs(staffMember('Journal Entry View List'))
        ->get(route('admin.accounting.journal-entries.index', ['filter' => ['status' => 'posted', 'search' => 'rent']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.description', 'Office rent'),
        );
});

test('a journal voucher prints as a PDF for those who may view the entries', function (string $currency, string $rate) {
    $entry = JournalEntryBuilder::make(now(), 'Office rent')
        ->currency(Currency::query()->where('code', $currency)->sole(), $rate)
        ->debit(account('5203'), 400)
        ->credit(account('110101'), 400)
        ->post();

    $response = $this->actingAs(staffMember('Journal Entry View List'))
        ->get(route('admin.accounting.journal-entries.pdf', $entry))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF');

    $this->actingAs(staffMember())->get(route('admin.accounting.journal-entries.pdf', $entry))->assertForbidden();
})->with(['base currency' => ['JOD', '1'], 'foreign currency' => ['USD', '0.71']]);
