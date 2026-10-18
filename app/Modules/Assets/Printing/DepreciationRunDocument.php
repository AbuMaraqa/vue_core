<?php

namespace App\Modules\Assets\Printing;

use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Printing\FinancialDocument;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Assets\Models\DepreciationRun;

/**
 * The printed monthly depreciation run: the depreciation each asset took and
 * the total posted in the journal.
 */
class DepreciationRunDocument extends FinancialDocument
{
    protected Currency $baseCurrency;

    public function __construct(protected DepreciationRun $run)
    {
        $run->loadMissing(['depreciations.asset.category', 'journalEntries', 'creator']);

        $this->baseCurrency = app(Currencies::class)->base();
    }

    public function title(): string
    {
        return __('Depreciation run');
    }

    public function number(): ?string
    {
        return $this->run->number;
    }

    public function status(): ?array
    {
        return $this->documentStatus($this->run->status);
    }

    public function details(): array
    {
        return [
            __('Month') => $this->run->period_end->translatedFormat('F Y'),
            __('Period end') => self::date($this->run->period_end),
            __('Currency') => $this->baseCurrency->code,
            __('Journal entries') => $this->run->journalEntries->map(fn (JournalEntry $entry): ?string => $entry->number)->filter()->implode(', '),
            __('Created by') => $this->run->creator?->name,
        ];
    }

    public function signatures(): array
    {
        return [__('Prepared by'), __('Approved by')];
    }

    protected function view(): string
    {
        return 'assets::print.depreciation-run';
    }

    protected function data(): array
    {
        return [
            'run' => $this->run,
            'baseCurrency' => $this->baseCurrency,
        ];
    }
}
