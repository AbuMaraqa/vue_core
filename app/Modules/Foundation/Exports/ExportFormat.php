<?php

namespace App\Modules\Foundation\Exports;

use App\Modules\Foundation\Concerns\EnumOptions;
use Maatwebsite\Excel\Excel;

/**
 * The file types a list can be exported to.
 */
enum ExportFormat: string
{
    use EnumOptions;

    case Xlsx = 'xlsx';
    case Csv = 'csv';
    case Pdf = 'pdf';

    public function label(): string
    {
        return match ($this) {
            self::Xlsx => __('Excel'),
            self::Csv => 'CSV',
            self::Pdf => 'PDF',
        };
    }

    /**
     * The maatwebsite/excel writer of spreadsheet formats. PDFs are rendered
     * by the Exporter itself, so they have none.
     */
    public function writerType(): ?string
    {
        return match ($this) {
            self::Xlsx => Excel::XLSX,
            self::Csv => Excel::CSV,
            self::Pdf => null,
        };
    }
}
