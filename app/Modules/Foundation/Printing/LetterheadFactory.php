<?php

namespace App\Modules\Foundation\Printing;

use App\Modules\Core\Models\Site;
use App\Modules\Core\Settings\AppearanceSettings;
use App\Modules\Core\Settings\GeneralSettings;
use InvalidArgumentException;

/**
 * Builds the letterhead from the site settings and the details modules add.
 *
 * Only class names are kept here, so the singleton holds no request state;
 * sources and settings are resolved each time a document is printed.
 */
class LetterheadFactory
{
    /**
     * @var list<class-string<LetterheadSource>>
     */
    protected array $sources = [];

    /**
     * @param  class-string<LetterheadSource>  ...$sources
     */
    public function register(string ...$sources): void
    {
        foreach ($sources as $source) {
            if (! is_subclass_of($source, LetterheadSource::class)) {
                throw new InvalidArgumentException("{$source} must implement ".LetterheadSource::class.'.');
            }

            if (! in_array($source, $this->sources, true)) {
                $this->sources[] = $source;
            }
        }
    }

    public function make(): Letterhead
    {
        $general = app(GeneralSettings::class);
        $print = app(PrintSettings::class);

        $details = [
            'Phone' => $general->contact_phone,
            'Email' => $general->contact_email,
        ];

        foreach ($this->sources as $source) {
            $details = [...$details, ...app($source)->details()];
        }

        return new Letterhead(
            name: $general->site_name,
            tagline: $general->site_description,
            logoPath: $print->show_logo ? $this->logoPath() : null,
            details: $this->translate($details),
            brandColor: app(AppearanceSettings::class)->primary_color,
            footerNote: $print->footer_note,
        );
    }

    /**
     * The logo as a local file, which mpdf embeds directly. Logos kept on a
     * remote disk are left out rather than downloaded while printing.
     */
    protected function logoPath(): ?string
    {
        $path = Site::current()->getFirstMedia(Site::LOGO)?->getPath();

        return $path !== null && is_file($path) ? $path : null;
    }

    /**
     * @param  array<string, string|null>  $details
     * @return list<array{label: string, value: string}>
     */
    protected function translate(array $details): array
    {
        $translated = [];

        foreach ($details as $label => $value) {
            if (filled($value)) {
                $translated[] = ['label' => __($label), 'value' => (string) $value];
            }
        }

        return $translated;
    }
}
