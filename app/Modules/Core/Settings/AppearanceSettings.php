<?php

namespace App\Modules\Core\Settings;

use App\Modules\Core\Enums\FontFamily;
use Spatie\LaravelSettings\Settings;

class AppearanceSettings extends Settings
{
    public FontFamily $font_family;

    /**
     * The brand color as a hex value; every shade of the interface is derived from it.
     */
    public string $primary_color;

    public static function group(): string
    {
        return 'appearance';
    }

    /**
     * Get the theme shared with every page.
     *
     * @return array{font: string, fontVariable: string, primaryColor: string}
     */
    public function theme(): array
    {
        return [
            'font' => $this->font_family->value,
            'fontVariable' => $this->font_family->cssVariable(),
            'primaryColor' => $this->primary_color,
        ];
    }
}
