<?php

namespace App\Modules\Accounting\Actions\JournalEntries;

use App\Modules\Accounting\Data\JournalEntryData;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaveJournalEntry
{
    public function __construct(protected Currencies $currencies) {}

    /**
     * Create a draft entry or replace the content of an existing draft.
     */
    public function handle(JournalEntry $entry, JournalEntryData $data): JournalEntry
    {
        return DB::transaction(function () use ($entry, $data): JournalEntry {
            $entry->fill([
                'date' => $data->date,
                'reference' => $data->reference,
                'description' => $data->description,
                'currency_id' => $data->currency_id,
                'exchange_rate' => $this->currencies->isBase($data->currency_id) ? '1' : $data->exchange_rate,
            ]);

            $userId = Auth::id();
            $entry->created_by ??= is_int($userId) ? $userId : null;
            $entry->save();

            $entry->lines()->delete();

            foreach ($data->lines as $position => $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                    'description' => $line['description'] ?? null,
                    'debit' => Decimal::round($line['debit'] ?? '0'),
                    'credit' => Decimal::round($line['credit'] ?? '0'),
                    'sort' => $position,
                ]);
            }

            return $entry;
        });
    }
}
