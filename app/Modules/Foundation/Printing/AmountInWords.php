<?php

namespace App\Modules\Foundation\Printing;

use ArPHP\I18N\Arabic;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use NumberFormatter;

/**
 * Write an amount in words, as printed on receipts and cheques:
 * "فقط ألف ومئتان وخمسون دينار و500/1000 لا غير".
 *
 * The fraction stays in digits, so any currency works without knowing the
 * name of its smaller unit.
 */
final class AmountInWords
{
    /**
     * @param  string  $currency  The translated name of the currency, e.g. "دينار أردني".
     */
    public static function spell(string|int|float $amount, string $currency, int $decimals = 2, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $value = BigDecimal::of((string) $amount)->abs()->toScale(max(0, $decimals), RoundingMode::HalfUp);

        [$integer, $fraction] = array_pad(explode('.', (string) $value), 2, '');

        $parameters = [
            'amount' => self::integerInWords((int) $integer, $locale),
            'currency' => $currency,
            'fraction' => ltrim($fraction, '0').'/1'.str_repeat('0', $decimals),
        ];

        return (string) ((int) $fraction > 0
            ? __('Only :amount :currency and :fraction', $parameters, $locale)
            : __('Only :amount :currency', $parameters, $locale));
    }

    protected static function integerInWords(int $number, string $locale): string
    {
        if ($locale === 'ar') {
            return (new Arabic)->int2str($number);
        }

        return (string) (new NumberFormatter($locale, NumberFormatter::SPELLOUT))->format($number);
    }
}
