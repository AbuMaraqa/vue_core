<?php

namespace App\Modules\Accounting\Reports;

use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Foundation\Exports\Report;
use App\Modules\Foundation\Support\Filters\DateFilter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every posted line of an account in a period, with the running balance:
 * the account statement accountants reconcile against.
 */
class GeneralLedger
{
    /**
     * Very long ledgers are cut, with a notice, to keep the page responsive.
     */
    public const int MAX_LINES = 5000;

    public function __construct(protected AccountBalances $balances) {}

    /**
     * @return array{account: array<string, mixed>, opening: string, closing: string, total_debit: string, total_credit: string, lines: list<array<string, mixed>>, truncated: bool}
     */
    public function generate(Account $account, CarbonInterface $from, CarbonInterface $to, ?int $costCenterId = null): array
    {
        $opening = $this->balances->movements(null, $from->copy()->subDay(), [$account->id], $costCenterId)[$account->id]['balance'] ?? '0';

        $lines = JournalLine::query()
            ->with(['entry', 'partner', 'costCenter'])
            ->where('journal_lines.account_id', $account->id)
            ->when($costCenterId !== null, fn (Builder $query) => $query->where('journal_lines.cost_center_id', $costCenterId))
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_entries.status', [JournalEntryStatus::Posted->value, JournalEntryStatus::Reversed->value])
            ->tap(fn (Builder $query) => DateFilter::between($query, 'journal_entries.date', $from, $to))
            ->orderBy('journal_entries.date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.sort')
            ->select('journal_lines.*')
            ->limit(self::MAX_LINES + 1)
            ->get();

        $running = Decimal::of($opening);
        $debits = Decimal::of(0);
        $credits = Decimal::of(0);
        $rows = [];

        foreach ($lines->take(self::MAX_LINES) as $line) {
            $running = $running->plus((string) $line->base_debit)->minus((string) $line->base_credit);
            $debits = $debits->plus((string) $line->base_debit);
            $credits = $credits->plus((string) $line->base_credit);

            $rows[] = [
                'entry_id' => $line->journal_entry_id,
                'date' => $line->entry->date->toDateString(),
                'number' => $line->entry->number,
                'description' => $line->description ?? $line->entry->description,
                'reference' => $line->entry->reference,
                'partner' => $line->partner?->getAttribute('name'),
                'cost_center' => $line->costCenter?->label(),
                'debit' => Decimal::round((string) $line->base_debit),
                'credit' => Decimal::round((string) $line->base_credit),
                'balance' => Decimal::round($running),
            ];
        }

        return [
            'account' => ['id' => $account->id, 'label' => $account->label(), 'type' => $account->type->value],
            'opening' => Decimal::round($opening),
            'closing' => Decimal::round($running),
            'total_debit' => Decimal::round($debits),
            'total_credit' => Decimal::round($credits),
            'lines' => $rows,
            'truncated' => $lines->count() > self::MAX_LINES,
        ];
    }

    public function export(Account $account, CarbonInterface $from, CarbonInterface $to, ?int $costCenterId = null): Report
    {
        $report = $this->generate($account, $from, $to, $costCenterId);

        $rows = [['', '', __('Opening balance'), '', '', '', $report['opening']]];

        foreach ($report['lines'] as $line) {
            $rows[] = [$line['date'], $line['number'], $line['description'], $line['partner'], $line['debit'], $line['credit'], $line['balance']];
        }

        $rows[] = ['', '', __('Closing balance'), '', $report['total_debit'], $report['total_credit'], $report['closing']];

        return new Report(
            'General Ledger',
            ['Date', 'Number', 'Description', 'Partner', 'Debit', 'Credit', 'Balance'],
            $rows,
            [$account->label(), __(':from to :to', ['from' => $from->toDateString(), 'to' => $to->toDateString()])],
        );
    }
}
