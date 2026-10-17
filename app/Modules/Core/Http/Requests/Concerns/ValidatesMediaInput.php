<?php

namespace App\Modules\Core\Http\Requests\Concerns;

use App\Modules\Core\Rules\ValidTemporaryUpload;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Validation rules for a field sent by the MediaUpload component.
 */
trait ValidatesMediaInput
{
    /**
     * @param  list<string>  $mimeTypes
     * @return array<string, list<Exists|ValidTemporaryUpload|string>>
     */
    protected function mediaRules(string $field, array $mimeTypes, int $maxKilobytes, ?int $maxFiles = null): array
    {
        $listRules = $maxFiles === null ? ['array'] : ['array', "max:{$maxFiles}"];

        return [
            $field => ['sometimes', 'array:uploads,library,removed'],
            "{$field}.uploads" => $listRules,
            "{$field}.uploads.*" => ['string', new ValidTemporaryUpload($mimeTypes, $maxKilobytes)],
            "{$field}.library" => $listRules,
            "{$field}.library.*" => ['integer', Rule::exists(Media::class, 'id')->whereIn('mime_type', $mimeTypes)],
            "{$field}.removed" => ['array'],
            "{$field}.removed.*" => ['integer'],
        ];
    }
}
