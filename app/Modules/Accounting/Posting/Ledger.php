<?php

namespace App\Modules\Accounting\Posting;

use App\Modules\Accounting\Enums\DocumentType;
use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Settings\AccountingSettings;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DocumentNumbers;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The only way into the books. Posting checks every rule of double-entry
 * bookkeeping, converts the amounts to the base currency and numbers the
 * entry; after that the entry can only be undone by a reversing entry.
 */
class Ledger
{
    public function __construct(
        protected Currencies $currencies,
        protected DocumentNumbers $numbers,
        protected AccountingSettings $settings,
    ) {}

    public function post(JournalEntry $entry): JournalEntry
    {
        return DB::transaction(function () use ($entry): JournalEntry {
            $entry = JournalEntry::query()->lockForUpdate()->with(['lines.account', 'currency'])->findOrFail($entry->id);

            if (! $entry->isDraft()) {
                throw new AccountingException(__('Only draft entries can be posted.'));
            }

            $fiscalYear = $this->ensureDateIsOpen($entry->date);

            $this->ensureLinesAreValid($entry);
            $this->convertToBase($entry);

            $entry->forceFill([
                'number' => $this->numbers->next(DocumentType::JournalEntry, $entry->date),
                'fiscal_year_id' => $fiscalYear->id,
                'status' => JournalEntryStatus::Posted,
                'total' => (string) Decimal::sum($entry->lines->pluck('debit')),
                'base_total' => (string) Decimal::sum($entry->lines->pluck('base_debit')),
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ])->save();

            return $entry;
        });
    }

    /**
     * Undo a posted entry with an entry of opposite amounts on the given date.
     * Both stay in the books, so the history is never lost.
     */
    public function reverse(JournalEntry $entry, CarbonInterface|string|null $date = null, ?string $description = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $date, $description): JournalEntry {
            $entry = JournalEntry::query()->lockForUpdate()->with(['lines', 'currency'])->findOrFail($entry->id);

            if ($entry->status !== JournalEntryStatus::Posted) {
                throw new AccountingException(__('Only posted entries can be reversed.'));
            }

            if ($entry->lines->contains(fn (JournalLine $line): bool => $line->reconciliation_id !== null)) {
                throw new AccountingException(__('The entry :number is reconciled with a bank statement. Undo the reconciliation first.', ['number' => $entry->number]));
            }

            $reversal = JournalEntry::query()->create([
                'date' => Carbon::parse($date ?? now())->toDateString(),
                'reference' => $entry->number,
                'description' => $description ?? __('Reversal of :number', ['number' => $entry->number]),
                'currency_id' => $entry->currency_id,
                'exchange_rate' => $entry->exchange_rate,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'reversal_of_id' => $entry->id,
                'created_by' => Auth::id(),
            ]);

            foreach ($entry->lines as $line) {
                $reversal->lines()->create([
                    'account_id' => $line->account_id,
                    'cost_center_id' => $line->cost_center_id,
                    'partner_type' => $line->partner_type,
                    'partner_id' => $line->partner_id,
                    'description' => $line->description,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'sort' => $line->sort,
                ]);
            }

            $this->post($reversal);

            $entry->forceFill(['status' => JournalEntryStatus::Reversed])->save();

            return $reversal->refresh();
        });
    }

    /**
     * Reverse every posted entry of a document, e.g. when it is cancelled.
     *
     * @return list<JournalEntry> The reversing entries.
     */
    public function reverseEntriesOf(Model $source, CarbonInterface|string|null $date = null, ?string $description = null): array
    {
        return DB::transaction(fn (): array => array_values(JournalEntry::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('status', JournalEntryStatus::Posted)
            ->whereNull('reversal_of_id')
            ->orderBy('id')
            ->get()
            ->map(fn (JournalEntry $entry): JournalEntry => $this->reverse($entry, $date, $description))
            ->all()));
    }

    /**
     * The date to reverse or cancel a document on: its own date while that
     * period is open, so both sides fall in the same period, else today.
     */
    public function openDateFor(CarbonInterface|string $date): string
    {
        try {
            $this->ensureDateIsOpen($date);

            return Carbon::parse($date)->toDateString();
        } catch (AccountingException) {
            return now()->toDateString();
        }
    }

    /**
     * Check that something can be posted on the date: after the lock date and
     * inside an open fiscal year. Documents call it before they post.
     */
    public function ensureDateIsOpen(CarbonInterface|string $date): FiscalYear
    {
        $day = Carbon::parse($date)->startOfDay();
        $lockDate = $this->settings->lock_date;

        if ($lockDate !== null && $day->lessThanOrEqualTo(Carbon::parse($lockDate))) {
            throw new AccountingException(__('The books are locked up to :date.', ['date' => $lockDate]));
        }

        $fiscalYear = FiscalYear::query()->containing($day)->first();

        if ($fiscalYear === null) {
            throw new AccountingException(__('There is no fiscal year for :date. Create it first.', ['date' => $day->toDateString()]));
        }

        if (! $fiscalYear->isOpen()) {
            throw new AccountingException(__('The fiscal year :name is closed.', ['name' => $fiscalYear->name]));
        }

        return $fiscalYear;
    }

    protected function ensureLinesAreValid(JournalEntry $entry): void
    {
        if ($entry->lines->count() < 2) {
            throw new AccountingException(__('A journal entry needs at least two lines.'));
        }

        foreach ($entry->lines as $line) {
            $debit = Decimal::of($line->debit);
            $credit = Decimal::of($line->credit);

            if ($debit->isNegative() || $credit->isNegative() || $debit->isZero() === $credit->isZero()) {
                throw new AccountingException(__('Each line needs either a debit or a credit amount.'));
            }

            if ($line->account->is_group || ! $line->account->is_active) {
                throw new AccountingException(__('The account :account cannot receive postings.', ['account' => $line->account->label()]));
            }

            if ($line->account->currency_id !== null && $line->account->currency_id !== $entry->currency_id) {
                throw new AccountingException(__('The account :account only takes amounts in its own currency.', ['account' => $line->account->label()]));
            }
        }

        $debits = Decimal::sum($entry->lines->pluck('debit'));
        $credits = Decimal::sum($entry->lines->pluck('credit'));

        if (! $debits->isEqualTo($credits)) {
            throw new AccountingException(__('The entry is not balanced: debits :debits, credits :credits.', [
                'debits' => (string) $debits,
                'credits' => (string) $credits,
            ]));
        }
    }

    /**
     * Convert each line with the rate of the entry. Rounding each line can
     * leave the base totals a few units apart; that difference is absorbed by
     * the largest line, so the entry stays balanced in both currencies.
     */
    protected function convertToBase(JournalEntry $entry): void
    {
        $rate = (string) $entry->exchange_rate;

        foreach ($entry->lines as $line) {
            $line->base_debit = $this->currencies->toBase((string) $line->debit, $rate);
            $line->base_credit = $this->currencies->toBase((string) $line->credit, $rate);
        }

        $difference = Decimal::sum($entry->lines->pluck('base_debit'))->minus(Decimal::sum($entry->lines->pluck('base_credit')));

        if (! $difference->isZero()) {
            $this->absorbRoundingDifference($entry, $difference);
        }

        $entry->lines->each(fn (JournalLine $line) => $line->save());
    }

    protected function absorbRoundingDifference(JournalEntry $entry, BigDecimal $difference): void
    {
        $side = $difference->isPositive() ? 'base_credit' : 'base_debit';

        /** @var JournalLine $largest */
        $largest = $entry->lines
            ->filter(fn (JournalLine $line): bool => Decimal::isPositive($line->{$side}))
            ->sort(fn (JournalLine $first, JournalLine $second): int => Decimal::of($second->{$side})->compareTo(Decimal::of($first->{$side})))
            ->first();

        $largest->{$side} = Decimal::round(Decimal::of($largest->{$side})->plus($difference->abs()));
    }
}
