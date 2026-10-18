<?php

namespace App\Modules\Core\Support\Localization;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Mcamara\LaravelLocalization\LaravelLocalization;

/**
 * A thin layer over laravel-localization that exposes the supported locales
 * in the shape the frontend needs.
 */
class Locales
{
    /**
     * Scripts written from right to left.
     */
    protected const array RTL_SCRIPTS = ['Arab', 'Hebr', 'Mong', 'Tfng', 'Thaa'];

    public function __construct(protected LaravelLocalization $localization) {}

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_values(array_filter(array_keys($this->supported()), is_string(...)));
    }

    public function isSupported(?string $locale): bool
    {
        return is_string($locale) && in_array($locale, $this->codes(), true);
    }

    /**
     * Apply the locale to the application, the translator and the PHP regional settings.
     */
    public function apply(string $locale): void
    {
        $this->localization->setLocale($locale);
    }

    public function direction(?string $locale = null): string
    {
        $script = Arr::get($this->supported(), ($locale ?? app()->getLocale()).'.script');

        return in_array($script, self::RTL_SCRIPTS, true) ? 'rtl' : 'ltr';
    }

    /**
     * @return list<array{code: string, name: string, native: string, direction: string}>
     */
    public function all(): array
    {
        $supported = $this->supported();

        return array_map(fn (string $code): array => [
            'code' => $code,
            'name' => Arr::string($supported, "{$code}.name"),
            'native' => Arr::string($supported, "{$code}.native"),
            'direction' => $this->direction($code),
        ], $this->codes());
    }

    /**
     * Get the JSON translations of the given locale for the frontend.
     *
     * @return array<string, string>
     */
    public function translations(?string $locale = null): array
    {
        $path = lang_path(($locale ?? app()->getLocale()).'.json');

        return File::exists($path) ? File::json($path) : [];
    }

    /**
     * Identify the translations the browser holds, so they are sent once and
     * sent again only after a language switch or an edit of the JSON file.
     */
    public function translationsVersion(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $path = lang_path($locale.'.json');

        return 'translations-'.$locale.'-'.(File::exists($path) ? File::lastModified($path) : 0);
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function supported(): array
    {
        return $this->localization->getSupportedLocales();
    }
}
