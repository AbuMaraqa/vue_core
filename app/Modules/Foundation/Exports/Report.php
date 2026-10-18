<?php

namespace App\Modules\Foundation\Exports;

use App\Modules\Foundation\Printing\PdfRenderer;
use App\Modules\Foundation\Printing\TableDocument;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Download a table that is computed rather than queried, e.g. a trial balance,
 * in the same Excel, CSV and PDF formats as list exports.
 *
 * ```php
 * return (new Report('Trial balance', ['Account', 'Debit', 'Credit'], $rows, ['1 Jan 2026 – 31 Dec 2026']))
 *     ->download($request->exportFormat());
 * ```
 *
 * The title and headings are English translation keys.
 */
final class Report
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<string|int|float|bool|null>>  $rows
     * @param  list<string>  $summary  Lines under the title in the PDF, already translated.
     */
    public function __construct(
        public string $title,
        public array $headings,
        public array $rows,
        public array $summary = [],
    ) {}

    public function download(ExportFormat $format): Response
    {
        $filename = Str::slug($this->title).'-'.now()->format('Y-m-d-His').'.'.$format->value;
        $headings = array_map(fn (string $heading): string => __($heading), $this->headings);

        if ($format === ExportFormat::Pdf) {
            $document = new TableDocument(__($this->title), $headings, $this->rows, $this->summary, filename: $filename);

            return app(PdfRenderer::class)->response($document, download: true);
        }

        return Excel::download(new TableExport($headings, $this->rows), $filename, $format->writerType());
    }
}
