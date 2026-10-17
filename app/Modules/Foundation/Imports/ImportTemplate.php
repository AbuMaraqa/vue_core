<?php

namespace App\Modules\Foundation\Imports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * An empty spreadsheet with the columns an Importer expects.
 *
 * @internal Use Importer::template().
 */
final class ImportTemplate implements FromArray, WithHeadings
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(private array $columns) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->columns;
    }
}
