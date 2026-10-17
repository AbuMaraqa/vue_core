<?php

namespace App\Modules\Foundation\Exports;

use App\Modules\Core\Support\Localization\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The maatwebsite/excel side of an Exporter: streams the query in chunks, so
 * large lists do not exhaust memory. Sheets are right-to-left in RTL languages.
 *
 * @internal Use Exporter::download().
 */
final class SpreadsheetExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  Exporter<Model>  $exporter
     */
    public function __construct(private Exporter $exporter) {}

    /**
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        return $this->exporter->query();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->exporter->headings();
    }

    /**
     * @param  Model  $row
     * @return list<string|int|float|bool|null>
     */
    public function map(mixed $row): array
    {
        return $this->exporter->row($row);
    }

    /**
     * @return array<int, array{font: array{bold: bool}}>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $event->sheet->getDelegate()->setRightToLeft(app(Locales::class)->direction() === 'rtl');
            },
        ];
    }
}
