<?php

namespace App\Modules\Core\Rules;

use App\Modules\Core\Models\TemporaryUpload;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validate a temporary upload id: it must belong to the current user and match
 * the accepted file types and size of the field.
 */
class ValidTemporaryUpload implements ValidationRule
{
    /**
     * @param  list<string>  $mimeTypes
     */
    public function __construct(
        protected array $mimeTypes = [],
        protected ?int $maxKilobytes = null,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $upload = is_string($value)
            ? TemporaryUpload::query()->whereKey($value)->where('user_id', Auth::id())->first()
            : null;

        if (! $upload) {
            $fail(__('The uploaded file has expired, please upload it again.'));

            return;
        }

        if ($this->mimeTypes !== [] && ! in_array($upload->mime_type, $this->mimeTypes, true)) {
            $fail(__('The file type of :name is not allowed.', ['name' => $upload->original_name]));

            return;
        }

        if ($this->maxKilobytes !== null && $upload->size > $this->maxKilobytes * 1024) {
            $fail(__('The file :name must not be larger than :max kilobytes.', ['name' => $upload->original_name, 'max' => $this->maxKilobytes]));
        }
    }
}
