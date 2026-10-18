<?php

use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\ExchangeRate;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Settings\AccountingSettings;
use App\Modules\Accounting\Support\Currencies;

beforeEach(function () {
    seedAccounting();
});

test('a balanced entry is posted, numbered and converted to the base currency', function () {
    $entry = JournalEntryBuilder::make(now(), 'Owner investment')
        ->debit(account('110101'), '1500.500')
        ->credit(account('3101'), '1500.500')
        ->post();

    expect($entry->status)->toBe(JournalEntryStatus::Posted)
        ->and($entry->number)->toBe('JE-'.now()->year.'-00001')
        ->and($entry->fiscal_year_id)->toBe(FiscalYear::query()->sole()->id)
        ->and((string) $entry->total)->toBe('1500.5000')
        ->and((string) $entry->base_total)->toBe('1500.5000')
        ->and($entry->lines->pluck('base_debit')->map(fn ($value) => (string) $value)->all())->toBe(['1500.5000', '0.0000']);
});

test('numbers are sequential and restart every year', function () {
    FiscalYear::factory()->year(now()->year + 1)->create();

    $post = fn (string $date) => JournalEntryBuilder::make($date)->debit(account('110101'), 10)->credit(account('3101'), 10)->post()->number;

    expect($post(now()->toDateString()))->toEndWith('-00001')
        ->and($post(now()->toDateString()))->toEndWith('-00002')
        ->and($post((now()->year + 1).'-01-05'))->toBe('JE-'.(now()->year + 1).'-00001');
});

test('the number prefix can be changed in the settings', function () {
    $settings = app(AccountingSettings::class);
    $settings->number_prefixes = ['journal_entry' => 'GJ'];
    $settings->save();

    $entry = JournalEntryBuilder::make(now())->debit(account('110101'), 10)->credit(account('3101'), 10)->post();

    expect($entry->number)->toStartWith('GJ-');
});

test('a negative amount moves to the other side', function () {
    $entry = JournalEntryBuilder::make(now())->debit(account('110101'), '-25')->credit(account('3101'), '-25')->post();

    expect((string) $entry->lines[0]->credit)->toBe('25.0000')
        ->and((string) $entry->lines[1]->debit)->toBe('25.0000');
});

test('rules of the books are enforced', function (Closure $build, string $message) {
    expect(fn () => $build()->post())->toThrow(AccountingException::class, $message);
})->with([
    'unbalanced' => [fn () => JournalEntryBuilder::make(now())->debit(account('110101'), 100)->credit(account('3101'), 90), 'not balanced'],
    'single line' => [fn () => JournalEntryBuilder::make(now())->debit(account('110101'), 100), 'at least two lines'],
    'group account' => [fn () => JournalEntryBuilder::make(now())->debit(account('1101'), 100)->credit(account('3101'), 100), 'cannot receive postings'],
    'no fiscal year' => [fn () => JournalEntryBuilder::make(now()->subYears(3))->debit(account('110101'), 100)->credit(account('3101'), 100), 'no fiscal year'],
]);

test('an inactive account cannot receive postings', function () {
    account('5299')->update(['is_active' => false]);

    expect(fn () => JournalEntryBuilder::make(now())->debit(account('5299'), 10)->credit(account('110101'), 10)->post())
        ->toThrow(AccountingException::class, 'cannot receive postings');
});

test('nothing can be posted on or before the lock date', function () {
    $settings = app(AccountingSettings::class);
    $settings->lock_date = now()->toDateString();
    $settings->save();

    expect(fn () => JournalEntryBuilder::make(now())->debit(account('110101'), 10)->credit(account('3101'), 10)->post())
        ->toThrow(AccountingException::class, 'locked');

    expect(JournalEntryBuilder::make(now()->addDay()->min(now()->endOfYear()))->debit(account('110101'), 10)->credit(account('3101'), 10)->post()->status)
        ->toBe(JournalEntryStatus::Posted);
})->skip(fn (): bool => now()->isSameDay(now()->endOfYear()), 'The day after the lock date must be in the same fiscal year.');

test('nothing can be posted in a closed fiscal year', function () {
    FiscalYear::query()->update(['status' => 'closed']);

    expect(fn () => JournalEntryBuilder::make(now())->debit(account('110101'), 10)->credit(account('3101'), 10)->post())
        ->toThrow(AccountingException::class, 'is closed');
});

describe('foreign currencies', function () {
    beforeEach(function () {
        $this->usd = Currency::query()->where('code', 'USD')->sole();
        ExchangeRate::query()->create(['currency_id' => $this->usd->id, 'effective_date' => now()->subMonth(), 'rate' => '0.709']);
        ExchangeRate::query()->create(['currency_id' => $this->usd->id, 'effective_date' => now()->subDays(2), 'rate' => '0.7091']);
    });

    test('the latest rate on or before the date is used', function () {
        expect(app(Currencies::class)->rate($this->usd, now()))->toBe('0.70910000')
            ->and(app(Currencies::class)->rate($this->usd, now()->subDays(10)))->toBe('0.70900000');
    });

    test('an account kept in a currency only takes amounts in that currency', function () {
        account('110101')->update(['currency_id' => $this->usd->id]);

        expect(fn () => JournalEntryBuilder::make(now())->debit(account('110101'), 100)->credit(account('3101'), 100)->post())
            ->toThrow(AccountingException::class, 'own currency');

        $entry = JournalEntryBuilder::make(now())
            ->currency($this->usd, '0.7091')
            ->debit(account('110101'), 100)
            ->credit(account('3101'), 100)
            ->post();

        expect($entry->base_total)->toBe('70.9100');
    });

    test('a date before the first rate is refused', function () {
        expect(fn () => app(Currencies::class)->rate($this->usd, now()->subYear()))
            ->toThrow(AccountingException::class, 'no exchange rate');
    });

    test('rounding each line to the base currency never unbalances the entry', function () {
        $entry = JournalEntryBuilder::make(now())
            ->currency($this->usd, '0.7091')
            ->debit(account('110101'), '100')
            ->credit(account('4101'), '33.33')
            ->credit(account('4101'), '33.33')
            ->credit(account('4101'), '33.34')
            ->post();

        $baseDebits = $entry->lines->sum(fn ($line) => (float) $line->base_debit);
        $baseCredits = $entry->lines->sum(fn ($line) => (float) $line->base_credit);

        expect(round($baseDebits, 3))->toBe(70.91)
            ->and(round($baseCredits, 3))->toBe(70.91)
            ->and((string) $entry->total)->toBe('100.0000')
            ->and((string) $entry->base_total)->toBe('70.9100');
    });
});

describe('reversal', function () {
    test('a reversal mirrors the entry and marks it reversed', function () {
        $entry = JournalEntryBuilder::make(now())->debit(account('5203'), 300)->credit(account('110101'), 300)->post();

        $reversal = app(Ledger::class)->reverse($entry, now());

        expect($entry->fresh()->status)->toBe(JournalEntryStatus::Reversed)
            ->and($reversal->status)->toBe(JournalEntryStatus::Posted)
            ->and($reversal->reversal_of_id)->toBe($entry->id)
            ->and((string) $reversal->lines[0]->credit)->toBe('300.0000')
            ->and((string) $reversal->lines[1]->debit)->toBe('300.0000');
    });

    test('an entry is reversed only once and drafts are posted only once', function () {
        $entry = JournalEntryBuilder::make(now())->debit(account('5203'), 300)->credit(account('110101'), 300)->post();
        app(Ledger::class)->reverse($entry);

        expect(fn () => app(Ledger::class)->reverse($entry->fresh()))->toThrow(AccountingException::class)
            ->and(fn () => app(Ledger::class)->post($entry->fresh()))->toThrow(AccountingException::class);
    });
});
