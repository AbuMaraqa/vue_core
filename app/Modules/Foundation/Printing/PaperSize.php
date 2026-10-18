<?php

namespace App\Modules\Foundation\Printing;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * The paper sizes documents can be printed on.
 */
enum PaperSize: string
{
    use EnumOptions;

    case A4 = 'A4';
    case A5 = 'A5';
    case Letter = 'Letter';

    public function label(): string
    {
        return match ($this) {
            self::A4 => 'A4',
            self::A5 => 'A5',
            self::Letter => __('Letter'),
        };
    }

    /**
     * The page format mpdf expects, e.g. `A4` or `A4-L` in landscape.
     */
    public function format(Orientation $orientation): string
    {
        return $orientation === Orientation::Landscape ? "{$this->value}-L" : $this->value;
    }
}
