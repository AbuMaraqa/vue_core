<?php

namespace App\Modules\Accounting\Printing;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Foundation\Printing\PrintDocument;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;

/**
 * A printed financial document: invoices, payments, vouchers, entries,
 * stock documents, payslips... It adds the formatting every one of them
 * needs on top of the shared print layout.
 */
abstract class FinancialDocument extends PrintDocument
{
    /**
     * The badge of a document that is not in the books: drafts and cancelled
     * documents are marked so a printout is never mistaken for a final one.
     *
     * @return array{label: string, tone: 'neutral'|'success'|'warning'|'danger'}|null
     */
    protected function documentStatus(DocumentStatus $status): ?array
    {
        return match ($status) {
            DocumentStatus::Draft => ['label' => $status->label(), 'tone' => 'warning'],
            DocumentStatus::Cancelled => ['label' => $status->label(), 'tone' => 'danger'],
            DocumentStatus::Posted => null,
        };
    }

    /**
     * Format an amount with the decimals of its currency, e.g. `1,250.500`.
     */
    public static function money(string|int|float|null $amount, Currency|int $decimals = 2): string
    {
        $scale = $decimals instanceof Currency ? $decimals->decimal_places : $decimals;
        $value = BigDecimal::of((string) ($amount ?? 0))->toScale(max(0, $scale), RoundingMode::HalfUp);

        // Formatted from the decimal string, so large amounts keep every digit.
        [$integer, $fraction] = array_pad(explode('.', (string) $value->abs()), 2, '');
        $grouped = strrev(implode(',', str_split(strrev($integer), 3)));

        return ($value->isNegative() ? '-' : '').$grouped.($fraction !== '' ? ".{$fraction}" : '');
    }

    /**
     * Format a quantity without trailing zeros, e.g. `2.5`.
     */
    public static function quantity(string|int|float|null $quantity): string
    {
        $formatted = number_format((float) ($quantity ?? 0), 4);

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }

    /**
     * Format a percentage without trailing zeros, or an empty string when it is zero.
     */
    public static function percent(string|int|float|null $percent): string
    {
        return (float) $percent > 0 ? self::quantity($percent).'%' : '';
    }

    public static function date(?CarbonInterface $date): ?string
    {
        return $date?->toDateString();
    }
}
