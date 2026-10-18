<?php

namespace App\Modules\Foundation\Printing;

use Spatie\LaravelSettings\Settings;

/**
 * How every printed document looks: the paper, what the letterhead shows and
 * the note repeated at the bottom of each page.
 */
class PrintSettings extends Settings
{
    public PaperSize $paper_size;

    public bool $show_logo;

    /**
     * Whether the footer names the user who printed the document and when.
     */
    public bool $show_printed_by;

    /**
     * A short text printed at the bottom of every page, e.g. bank details or terms.
     */
    public ?string $footer_note;

    public static function group(): string
    {
        return 'print';
    }
}
