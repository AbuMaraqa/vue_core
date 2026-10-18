<?php

namespace App\Modules\Foundation\Exports;

use App\Modules\Foundation\Printing\PdfRenderer;
use App\Modules\Foundation\Printing\TableDocument;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export a list to Excel, CSV or PDF, with the same filters and sorting the
 * user applied on screen.
 *
 * A subclass names the export and lists its columns:
 *
 * ```php
 * class CategoriesExporter extends Exporter
 * {
 *     public function title(): string { return 'Categories'; }
 *
 *     public function columns(): array
 *     {
 *         return [
 *             'Name' => fn (Category $category) => $category->name,
 *             'Created at' => fn (Category $category) => $category->created_at?->format('Y-m-d H:i'),
 *         ];
 *     }
 * }
 *
 * return CategoriesExporter::from($this->query())->download($request->exportFormat());
 * ```
 *
 * Column labels and the title are English translation keys, so the file is
 * written in the language of the user who downloads it.
 *
 * @template TModel of Model
 */
abstract class Exporter
{
    /**
     * @param  Builder<TModel>  $query
     */
    final public function __construct(protected Builder $query) {}

    /**
     * Start an export from a query, or from the QueryBuilder of an index page.
     *
     * @param  Builder<TModel>|QueryBuilder<TModel>  $query
     */
    public static function from(Builder|QueryBuilder $query): static
    {
        return new static($query instanceof QueryBuilder ? $query->getEloquentBuilder() : $query);
    }

    /**
     * The name of the export, as an English translation key, e.g. `Users`.
     */
    abstract public function title(): string;

    /**
     * The columns, as translation keys mapped to the value of each record.
     *
     * @return array<string, Closure(TModel): (string|int|float|bool|null)>
     */
    abstract public function columns(): array;

    public function download(ExportFormat $format): Response
    {
        $filename = $this->filename($format);

        if ($format === ExportFormat::Pdf) {
            return $this->pdf($filename);
        }

        return Excel::download(new SpreadsheetExport($this), $filename, $format->writerType());
    }

    /**
     * @return Builder<TModel>
     */
    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_map(fn (string $label): string => __($label), array_keys($this->columns()));
    }

    /**
     * @param  TModel  $record
     * @return list<string|int|float|bool|null>
     */
    public function row(Model $record): array
    {
        return array_values(array_map(fn (Closure $value) => $value($record), $this->columns()));
    }

    public function filename(ExportFormat $format): string
    {
        return Str::slug($this->title()).'-'.now()->format('Y-m-d-His').'.'.$format->value;
    }

    /**
     * Render the rows into a printable table. PDFs are built in memory, so the
     * rows are capped at `foundation.exports.pdf_max_rows`.
     */
    protected function pdf(string $filename): Response
    {
        $limit = config()->integer('foundation.exports.pdf_max_rows');
        $records = $this->query()->limit($limit + 1)->get();

        $document = new TableDocument(
            title: __($this->title()),
            headings: $this->headings(),
            rows: $records->take($limit)->map(fn (Model $record): array => $this->row($record)),
            summary: [__(':count records', ['count' => min($records->count(), $limit)])],
            notice: $records->count() > $limit ? (string) __('Only the first :limit records are included. Export to Excel for the full list.', ['limit' => $limit]) : null,
            filename: $filename,
        );

        return app(PdfRenderer::class)->response($document, download: true);
    }
}
