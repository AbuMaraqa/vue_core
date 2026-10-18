<?php

namespace App\Modules\Foundation\Printing;

use App\Modules\Core\Enums\FontFamily;

/**
 * The interface fonts as TrueType files mpdf can embed, so a printed
 * document uses the same font as the screen. mpdf cannot read the web fonts
 * Vite serves, hence the separate copies in `resources/fonts`.
 */
final class PrintFonts
{
    public static function directory(): string
    {
        return dirname(__DIR__).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'fonts';
    }

    /**
     * The mpdf name of the font, e.g. `ibmplexsansarabic`.
     */
    public static function name(FontFamily $font): string
    {
        return str_replace('-', '', $font->value);
    }

    /**
     * The mpdf `fontdata` of every interface font. OTL tables are enabled so
     * Arabic letters are joined, and kashida is used to justify Arabic text.
     *
     * @return array<string, array{R: string, B: string, useOTL: int, useKashida: int}>
     */
    public static function fontdata(): array
    {
        $fontdata = [];

        foreach (FontFamily::cases() as $font) {
            $file = self::file($font);

            $fontdata[self::name($font)] = [
                'R' => "{$file}-Regular.ttf",
                'B' => "{$file}-Bold.ttf",
                'useOTL' => 0xFF,
                'useKashida' => 75,
            ];
        }

        return $fontdata;
    }

    protected static function file(FontFamily $font): string
    {
        return match ($font) {
            FontFamily::IbmPlexSansArabic => 'IBMPlexSansArabic',
            FontFamily::Tajawal => 'Tajawal',
            FontFamily::Cairo => 'Cairo',
        };
    }
}
