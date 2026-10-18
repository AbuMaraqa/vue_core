<?php

namespace App\Modules\Foundation\Printing;

/**
 * The identity printed on every page: who issued the document and how to
 * reach them. Built fresh for each document by the LetterheadFactory.
 */
final readonly class Letterhead
{
    /**
     * @param  list<array{label: string, value: string}>  $details  Already translated, e.g. phone, address, tax number.
     */
    public function __construct(
        public string $name,
        public ?string $tagline,
        public ?string $logoPath,
        public array $details,
        public string $brandColor,
        public ?string $footerNote,
    ) {}
}
