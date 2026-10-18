<?php

namespace App\Modules\Accounting\Posting;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Build a journal entry line by line, then save it as a draft or post it.
 * Every document of the system writes its accounting effect through this
 * builder, so all entries follow the same rules.
 *
 * ```php
 * JournalEntryBuilder::make($invoice->issue_date, __('Sales invoice :number', ['number' => $invoice->number]))
 *     ->currency($invoice->currency, $invoice->exchange_rate)
 *     ->source($invoice)
 *     ->debit($receivable, $invoice->total, partner: $invoice->contact)
 *     ->credit($sales, $invoice->subtotal)
 *     ->credit($taxAccount, $invoice->tax_total)
 *     ->post();
 * ```
 *
 * A negative amount moves to the other side; zero amounts are skipped.
 */
final class JournalEntryBuilder
{
    /**
     * @var list<array{account_id: int, debit: string, credit: string, description: string|null, partner_type: string|null, partner_id: int|null, cost_center_id: int|null}>
     */
    private array $lines = [];

    private ?Currency $currency = null;

    private string $rate = '1';

    private ?string $reference = null;

    private ?Model $source = null;

    private function __construct(private string $date, private ?string $description) {}

    public static function make(CarbonInterface|string $date, ?string $description = null): self
    {
        return new self(Carbon::parse($date)->toDateString(), $description);
    }

    /**
     * The currency of the amounts, and how much one unit is worth in the base
     * currency. Defaults to the base currency.
     */
    public function currency(Currency $currency, string $rate): self
    {
        $this->currency = $currency;
        $this->rate = $rate;

        return $this;
    }

    public function reference(?string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * The document the entry belongs to.
     */
    public function source(Model $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function debit(Account|int $account, BigNumber|int|string|null $amount, ?string $description = null, ?Model $partner = null, ?int $costCenterId = null): self
    {
        return $this->line($account, Decimal::of($amount), $description, $partner, $costCenterId);
    }

    public function credit(Account|int $account, BigNumber|int|string|null $amount, ?string $description = null, ?Model $partner = null, ?int $costCenterId = null): self
    {
        return $this->line($account, Decimal::of($amount)->negated(), $description, $partner, $costCenterId);
    }

    public function hasLines(): bool
    {
        return $this->lines !== [];
    }

    /**
     * Save the entry as a draft, to be reviewed and posted later.
     */
    public function save(): JournalEntry
    {
        return DB::transaction(function (): JournalEntry {
            $currency = $this->currency ?? app(Currencies::class)->base();

            $entry = JournalEntry::query()->create([
                'date' => $this->date,
                'reference' => $this->reference,
                'description' => $this->description,
                'currency_id' => $currency->id,
                'exchange_rate' => $this->rate,
                'source_type' => $this->source?->getMorphClass(),
                'source_id' => $this->source?->getKey(),
                'created_by' => Auth::id(),
            ]);

            foreach ($this->lines as $position => $line) {
                $entry->lines()->create([...$line, 'sort' => $position]);
            }

            return $entry;
        });
    }

    /**
     * Save the entry and post it to the books.
     */
    public function post(): JournalEntry
    {
        return DB::transaction(fn (): JournalEntry => app(Ledger::class)->post($this->save()));
    }

    /**
     * A positive signed amount is a debit, a negative one a credit.
     */
    private function line(Account|int $account, BigDecimal $signedAmount, ?string $description, ?Model $partner, ?int $costCenterId): self
    {
        if ($signedAmount->isZero()) {
            return $this;
        }

        $this->lines[] = [
            'account_id' => $account instanceof Account ? $account->id : $account,
            'debit' => $signedAmount->isPositive() ? Decimal::round($signedAmount) : '0',
            'credit' => $signedAmount->isNegative() ? Decimal::round($signedAmount->abs()) : '0',
            'description' => $description,
            'partner_type' => $partner?->getMorphClass(),
            'partner_id' => $partner?->getKey(),
            'cost_center_id' => $costCenterId,
        ];

        return $this;
    }
}
