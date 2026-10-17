<?php

namespace App\Modules\Core\Data;

use Spatie\LaravelData\Data;

/**
 * The changes a media field asks for: new uploads, files picked from the
 * media library, and existing media to remove.
 */
class MediaInputData extends Data
{
    /**
     * @param  list<string>  $uploads  Temporary upload ids.
     * @param  list<int>  $library  Media ids picked from the media library.
     * @param  list<int>  $removed  Media ids of the collection to delete.
     */
    public function __construct(
        public array $uploads = [],
        public array $library = [],
        public array $removed = [],
    ) {}
}
