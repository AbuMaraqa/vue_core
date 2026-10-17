<?php

namespace App\Modules\Foundation\Imports;

/**
 * What an import did: how many rows were saved and why the others were not.
 */
final readonly class ImportResult
{
    /**
     * @param  list<array{row: int, column: string, errors: list<string>}>  $failures
     */
    public function __construct(
        public int $imported,
        public array $failures,
    ) {}

    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }

    /**
     * @return array{imported: int, failures: list<array{row: int, column: string, errors: list<string>}>}
     */
    public function toArray(): array
    {
        return [
            'imported' => $this->imported,
            'failures' => $this->failures,
        ];
    }
}
