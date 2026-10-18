<?php

namespace App\Modules\Accounting\Support;

use App\Modules\Accounting\Enums\DocumentType;
use App\Modules\Accounting\Models\DocumentSequence;
use App\Modules\Accounting\Settings\AccountingSettings;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gapless, yearly document numbers such as `INV-2026-00042`.
 *
 * The sequence row is locked while a number is taken, so two documents saved
 * at the same moment never share a number. Call it inside the transaction
 * that saves the document, so a failed save gives the number back.
 */
class DocumentNumbers
{
    public function __construct(protected AccountingSettings $settings) {}

    public function next(DocumentType $type, CarbonInterface|string $date): string
    {
        $year = Carbon::parse($date)->year;

        return DB::transaction(function () use ($type, $year): string {
            $sequence = $this->sequence($type, $year);
            $number = $sequence->next_number;

            $sequence->increment('next_number');

            return sprintf('%s-%d-%05d', $this->prefix($type), $year, $number);
        });
    }

    public function prefix(DocumentType $type): string
    {
        $prefix = $this->settings->number_prefixes[$type->value] ?? null;

        return filled($prefix) ? (string) $prefix : $type->defaultPrefix();
    }

    protected function sequence(DocumentType $type, int $year): DocumentSequence
    {
        $query = fn () => DocumentSequence::query()->where('type', $type->value)->where('year', $year)->lockForUpdate()->first();

        $sequence = $query();

        if ($sequence !== null) {
            return $sequence;
        }

        try {
            DocumentSequence::query()->create(['type' => $type->value, 'year' => $year, 'next_number' => 1]);
        } catch (UniqueConstraintViolationException) {
            // Another request created it first; use theirs.
        }

        return $query();
    }
}
