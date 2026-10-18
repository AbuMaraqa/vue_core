<?php

namespace App\Modules\Foundation\Printing;

/**
 * A list or a report printed as a table, used by list exports and reports.
 * Wide tables are printed in landscape.
 */
final class TableDocument extends PrintDocument
{
    /**
     * @param  string  $title  Already translated.
     * @param  list<string>  $headings  Already translated.
     * @param  iterable<list<string|int|float|bool|null>>  $rows
     * @param  list<string>  $summary  Lines shown above the table, e.g. the report period.
     */
    public function __construct(
        protected string $title,
        protected array $headings,
        protected iterable $rows,
        protected array $summary = [],
        protected ?string $notice = null,
        protected ?string $filename = null,
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    public function orientation(): Orientation
    {
        return count($this->headings) > 5 ? Orientation::Landscape : Orientation::Portrait;
    }

    public function downloadsByDefault(): bool
    {
        return true;
    }

    public function filename(): string
    {
        return $this->filename ?? parent::filename();
    }

    protected function view(): string
    {
        return 'foundation::print.table';
    }

    protected function data(): array
    {
        return [
            'headings' => $this->headings,
            'rows' => collect($this->rows),
            'summary' => $this->summary,
            'notice' => $this->notice,
        ];
    }
}
