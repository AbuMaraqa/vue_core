<?php

namespace App\Modules\Accounting\Printing;

use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DocumentLinks;
use App\Modules\Foundation\Printing\Orientation;

/**
 * The printed journal voucher: the debits and credits of an entry with their
 * accounts and cost centers, the totals and the amount in words.
 */
class JournalEntryDocument extends FinancialDocument
{
    protected Currency $baseCurrency;

    public function __construct(protected JournalEntry $entry)
    {
        $entry->loadMissing(['currency', 'lines.account', 'lines.costCenter', 'reversalOf', 'reversedBy', 'creator', 'poster', 'source']);

        $this->baseCurrency = app(Currencies::class)->base();
    }

    public function title(): string
    {
        return __('Journal voucher');
    }

    public function number(): ?string
    {
        return $this->entry->number;
    }

    public function status(): ?array
    {
        return match ($this->entry->status) {
            JournalEntryStatus::Draft => ['label' => $this->entry->status->label(), 'tone' => 'warning'],
            JournalEntryStatus::Reversed => ['label' => $this->entry->status->label(), 'tone' => 'danger'],
            JournalEntryStatus::Posted => null,
        };
    }

    public function details(): array
    {
        $entry = $this->entry;

        return [
            __('Date') => self::date($entry->date),
            __('Currency') => $this->isForeign()
                ? $entry->currency->code.' × '.rtrim(rtrim($entry->exchange_rate, '0'), '.')
                : $entry->currency->code,
            __('Reference') => $entry->reference,
            __('Source document') => $this->sourceDocument(),
            __('Reverses') => $entry->reversalOf?->number,
            __('Reversed by') => $entry->reversedBy?->number,
            __('Created by') => $entry->creator?->name,
            __('Posted by') => $entry->poster?->name,
        ];
    }

    public function signatures(): array
    {
        return [__('Prepared by'), __('Reviewed by'), __('Approved by')];
    }

    /**
     * A foreign currency entry also prints its base currency amounts, so it needs the width of a landscape page.
     */
    public function orientation(): Orientation
    {
        return $this->isForeign() ? Orientation::Landscape : Orientation::Portrait;
    }

    protected function view(): string
    {
        return 'accounting::print.journal-entry';
    }

    protected function data(): array
    {
        return [
            'entry' => $this->entry,
            'baseCurrency' => $this->baseCurrency,
            'isForeign' => $this->isForeign(),
            // A foreign entry is spelled out in the base currency, the currency of the books.
            'words' => $this->isForeign()
                ? ['amount' => $this->entry->base_total, 'currency' => $this->baseCurrency]
                : ['amount' => $this->entry->total, 'currency' => $this->entry->currency],
            'totals' => [
                'debit' => $this->sum('debit'),
                'credit' => $this->sum('credit'),
                'base_debit' => $this->sum('base_debit'),
                'base_credit' => $this->sum('base_credit'),
            ],
        ];
    }

    /**
     * The total of a column of the lines, e.g. `debit`.
     */
    protected function sum(string $column): string
    {
        return (string) Decimal::sum($this->entry->lines->pluck($column));
    }

    /**
     * Whether the amounts differ from the base currency ones, as on the Show page.
     */
    protected function isForeign(): bool
    {
        return ! Decimal::equals($this->entry->exchange_rate, 1);
    }

    /**
     * The kind and number of the document that wrote the entry, e.g. "Sales invoice INV-2026-0001".
     */
    protected function sourceDocument(): ?string
    {
        $source = $this->entry->source;

        if ($source === null) {
            return null;
        }

        $label = app(DocumentLinks::class)->for($source)['label'] ?? __(class_basename($source));
        $number = $source->getAttribute('number') ?? $source->getAttribute('name');

        return trim($label.' '.(is_scalar($number) ? $number : ''));
    }
}
