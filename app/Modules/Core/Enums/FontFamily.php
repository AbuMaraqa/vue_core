<?php

namespace App\Modules\Core\Enums;

/**
 * The interface fonts the store can choose from. Each one is loaded by Vite
 * and exposed as a `--font-{value}` CSS variable.
 */
enum FontFamily: string
{
    case IbmPlexSansArabic = 'ibm-plex-sans-arabic';
    case Tajawal = 'tajawal';
    case Cairo = 'cairo';

    public function label(): string
    {
        return match ($this) {
            self::IbmPlexSansArabic => 'IBM Plex Sans Arabic',
            self::Tajawal => 'Tajawal',
            self::Cairo => 'Cairo',
        };
    }

    public function cssVariable(): string
    {
        return "--font-{$this->value}";
    }

    /**
     * @return list<array{value: string, label: string, variable: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $font): array => [
            'value' => $font->value,
            'label' => $font->label(),
            'variable' => $font->cssVariable(),
        ], self::cases());
    }
}
