<?php

namespace App\Modules\Foundation\Printing;

use Illuminate\Support\HtmlString;

/**
 * mpdf lays out every run of text in the page direction, so in Arabic a phone
 * number such as "+970 599 000 000" comes out reversed. Text without Arabic
 * letters is wrapped in a left-to-right span to keep its order.
 */
final class Bidi
{
    public static function isolate(?string $text): HtmlString
    {
        $escaped = e((string) $text);

        if ($escaped === '' || preg_match('/\p{Arabic}/u', $escaped) === 1) {
            return new HtmlString($escaped);
        }

        return new HtmlString('<span dir="ltr">'.$escaped.'</span>');
    }
}
