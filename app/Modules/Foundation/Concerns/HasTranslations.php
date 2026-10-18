<?php

namespace App\Modules\Foundation\Concerns;

/**
 * Attributes stored in every language, as JSON: `{"ar": "النقدية", "en": "Cash"}`.
 *
 * The model casts each translatable attribute to `array` and lists it in
 * `$translatable`; `translate('name')` returns the value in the current
 * language, falling back to the other languages so a name is never empty.
 *
 * ```php
 * class Account extends Model
 * {
 *     use HasTranslations;
 *
 *     protected array $translatable = ['name'];
 *
 *     protected function casts(): array { return ['name' => 'array']; }
 * }
 * ```
 */
trait HasTranslations
{
    public function translate(string $attribute, ?string $locale = null): string
    {
        $values = $this->getAttribute($attribute);

        if (! is_array($values)) {
            return is_scalar($values) ? (string) $values : '';
        }

        $locale ??= app()->getLocale();

        foreach ([$locale, config('app.fallback_locale'), ...array_keys($values)] as $candidate) {
            if (is_string($candidate) && filled($values[$candidate] ?? null)) {
                return (string) $values[$candidate];
            }
        }

        return '';
    }

    /**
     * The JSON paths of a translatable attribute in every language, for search
     * filters: `SearchFilter::on('code', ...$account->translationColumns('name'))`.
     *
     * @return list<string>
     */
    public static function translationColumns(string $attribute): array
    {
        return array_map(
            fn (string $locale): string => "{$attribute}->{$locale}",
            array_keys(config()->array('laravellocalization.supportedLocales')),
        );
    }
}
