<?php

namespace App\Modules\Accounting\Actions\Currencies;

use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Events\BaseCurrencyChanged;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\ExchangeRate;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Settings\AccountingSettings;
use App\Modules\Accounting\Support\BaseCurrencyConversion;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Moves the books to another base currency at one rate: `1 old = $rate new`.
 *
 * - Every base amount of the ledger is worked out again from the entry's own
 *   amounts with its new rate, exactly as posting does.
 * - The old base currency becomes a foreign currency with that rate, the other
 *   currencies' rates are re-expressed in the new base, and documents in the
 *   new base currency get the rate 1.
 * - Every module converts the base amounts of its own tables (stock values,
 *   asset costs, salaries...) by listening to {@see BaseCurrencyChanged}.
 */
class ChangeBaseCurrency
{
    public function __construct(
        protected Currencies $currencies,
        protected AccountingSettings $settings,
    ) {}

    public function handle(Currency $to, string $rate): BaseCurrencyConversion
    {
        $from = $this->currencies->base();

        if ($to->is($from)) {
            throw new AccountingException(__(':code is already the base currency.', ['code' => $to->code]));
        }

        if (! $to->is_active) {
            throw new AccountingException(__('The currency :code is not active.', ['code' => $to->code]));
        }

        if (! Decimal::isPositive($rate)) {
            throw new AccountingException(__('The exchange rate must be greater than zero.'));
        }

        $conversion = new BaseCurrencyConversion($from, $to, Decimal::round($rate, 8));

        DB::transaction(function () use ($conversion): void {
            $this->convertExchangeRates($conversion);
            $this->convertJournalEntries($conversion);
            $conversion->convertColumns('budget_lines', ['amount' => Decimal::SCALE]);

            Account::query()->where('currency_id', $conversion->to->id)->update(['currency_id' => null]);

            BaseCurrencyChanged::dispatch($conversion);

            $this->settings->base_currency = $conversion->to->code;
            $this->settings->save();
        });

        return $conversion;
    }

    /**
     * The new base currency needs no rate; the old one gets the rate of the
     * change from the first day of the books; the others are re-expressed.
     */
    protected function convertExchangeRates(BaseCurrencyConversion $conversion): void
    {
        ExchangeRate::query()->whereIn('currency_id', [$conversion->from->id, $conversion->to->id])->delete();

        $conversion->convertColumns('exchange_rates', ['rate' => 8]);

        ExchangeRate::query()->create([
            'currency_id' => $conversion->from->id,
            'effective_date' => $this->firstDayOfTheBooks(),
            'rate' => $conversion->rate,
        ]);
    }

    protected function firstDayOfTheBooks(): string
    {
        return collect([
            FiscalYear::query()->min('start_date'),
            JournalEntry::query()->min('date'),
            now()->toDateString(),
        ])
            ->filter()
            ->map(fn (string $date): string => Carbon::parse($date)->toDateString())
            ->min();
    }

    /**
     * Work out the base amounts of each entry again with its new rate. As in
     * posting, the rounding difference goes to the largest line so the entry
     * stays balanced. Drafts only get their new rate; posting converts them.
     */
    protected function convertJournalEntries(BaseCurrencyConversion $conversion): void
    {
        $decimals = $conversion->to->decimal_places;

        JournalEntry::query()
            ->select(['id', 'currency_id', 'exchange_rate', 'status'])
            ->orderBy('id')
            ->chunkById(200, function (Collection $entries) use ($conversion, $decimals): void {
                $lines = DB::table('journal_lines')
                    ->whereIn('journal_entry_id', $entries->modelKeys())
                    ->orderBy('id')
                    ->get(['id', 'journal_entry_id', 'debit', 'credit'])
                    ->groupBy('journal_entry_id');

                foreach ($entries as $entry) {
                    $rate = $conversion->exchangeRate($entry->currency_id, (string) $entry->exchange_rate);

                    if ($entry->status === JournalEntryStatus::Draft) {
                        DB::table('journal_entries')->where('id', $entry->id)->update(['exchange_rate' => $rate]);

                        continue;
                    }

                    $converted = $this->convertLines($lines->get($entry->id, collect()), $rate, $decimals);

                    foreach ($converted as $id => $amounts) {
                        DB::table('journal_lines')->where('id', $id)->update($amounts);
                    }

                    DB::table('journal_entries')->where('id', $entry->id)->update([
                        'exchange_rate' => $rate,
                        'base_total' => (string) Decimal::sum(array_column($converted, 'base_debit')),
                    ]);
                }
            });
    }

    /**
     * @param  Collection<int, object{id: int, debit: string, credit: string}>  $lines
     * @return array<int, array{base_debit: string, base_credit: string}>
     */
    protected function convertLines(Collection $lines, string $rate, int $decimals): array
    {
        $converted = [];

        foreach ($lines as $line) {
            $converted[$line->id] = [
                'base_debit' => Decimal::round(Decimal::multiply($line->debit, $rate, $decimals)),
                'base_credit' => Decimal::round(Decimal::multiply($line->credit, $rate, $decimals)),
            ];
        }

        $difference = Decimal::sum(array_column($converted, 'base_debit'))->minus(Decimal::sum(array_column($converted, 'base_credit')));

        if ($difference->isZero()) {
            return $converted;
        }

        $side = $difference->isPositive() ? 'base_credit' : 'base_debit';
        $largest = collect($converted)
            ->filter(fn (array $amounts): bool => Decimal::isPositive($amounts[$side]))
            ->sort(fn (array $first, array $second): int => Decimal::of($second[$side])->compareTo(Decimal::of($first[$side])))
            ->keys()
            ->first();

        $converted[$largest][$side] = Decimal::round(Decimal::of($converted[$largest][$side])->plus($difference->abs()));

        return $converted;
    }
}
