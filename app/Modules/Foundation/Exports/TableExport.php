<?php

namespace App\Modules\Foundation\Exports;

use App\Modules\Core\Support\Localization\Locales;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * A spreadsheet built from rows already in memory, e.g. a financial report.
 *
 * @internal Use Report::download().
 */
final class TableExport implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<string|int|float|bool|null>>  $rows
     */
    public function __construct(private array $headings, private array $rows) {}

    /**
     * @return list<list<string|int|float|bool|null>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->headings;
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
