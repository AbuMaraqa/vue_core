<?php

namespace App\Modules\Foundation\Http\Requests\Concerns;

/**
 * Validation rules for a translatable attribute sent as `{ar: ..., en: ...}`:
 * every supported language is accepted and at least one is required.
 */
trait ValidatesTranslations
{
    /**
     * @return array<string, list<string>>
     */
    protected function translationRules(string $attribute, int $max = 255, bool $required = true): array
    {
        $locales = array_keys(config()->array('laravellocalization.supportedLocales'));

        $rules = [$attribute => [$required ? 'required' : 'nullable', 'array:'.implode(',', $locales)]];

        foreach ($locales as $locale) {
            $others = array_map(fn (string $other): string => "{$attribute}.{$other}", array_diff($locales, [$locale]));

            $rules["{$attribute}.{$locale}"] = [
                ...($required ? [$others === [] ? 'required' : 'required_without_all:'.implode(',', $others)] : []),
                'nullable',
                'string',
                "max:{$max}",
            ];
        }

        return $rules;
    }
}
