<?php

namespace App\Modules\Foundation\Imports;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\Failure;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Import records from an Excel or CSV file.
 *
 * The first row holds the column names given by `columns()`. Every row is
 * validated with `rules()`; valid rows are saved together in one transaction,
 * invalid rows are skipped and reported with their row number.
 *
 * ```php
 * class CategoriesImporter extends Importer
 * {
 *     public function columns(): array { return ['name', 'slug']; }
 *
 *     public function rules(): array
 *     {
 *         return ['name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'unique:categories,slug']];
 *     }
 *
 *     protected function importRow(array $row): void
 *     {
 *         Category::create($row);
 *     }
 * }
 * ```
 */
abstract class Importer implements SkipsEmptyRows, SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    private int $imported = 0;

    /**
     * @var list<array{row: int, column: string, errors: list<string>}>
     */
    private array $failures = [];

    /**
     * The column names of the file, in order. They form the template.
     *
     * @return list<string>
     */
    abstract public function columns(): array;

    /**
     * The validation rules of one row, keyed by column name.
     *
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * Save one valid row.
     *
     * @param  array<string, mixed>  $row
     */
    abstract protected function importRow(array $row): void;

    public function import(UploadedFile $file): ImportResult
    {
        Excel::import($this, $file);

        return new ImportResult($this->imported, $this->failures);
    }

    /**
     * Download an empty file with the expected columns, for users to fill in.
     */
    public function template(string $filename): BinaryFileResponse
    {
        return Excel::download(new ImportTemplate($this->columns()), $filename);
    }

    /**
     * Spreadsheet cells come back as numbers, booleans or padded text. Every
     * value is turned into trimmed text before validation, and empty cells
     * into null, so rules behave the same whatever the file looks like.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    public function prepareForValidation(array $data, int $index): array
    {
        return array_map(function (mixed $value): ?string {
            $text = is_scalar($value) ? trim((string) $value) : '';

            return $text === '' ? null : $text;
        }, $data);
    }

    /**
     * @param  Collection<int, Collection<string, mixed>>  $collection
     */
    public function collection(Collection $collection): void
    {
        DB::transaction(function () use ($collection): void {
            foreach ($collection as $row) {
                $this->importRow($row->only($this->columns())->all());
                $this->imported++;
            }
        });
    }

    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            $this->failures[] = [
                'row' => $failure->row(),
                'column' => $failure->attribute(),
                'errors' => $failure->errors(),
            ];
        }
    }
}
