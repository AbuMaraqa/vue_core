<?php

namespace App\Modules\Core\Settings;

use Spatie\LaravelSettings\Settings;

class LocalizationSettings extends Settings
{
    /**
     * The locale used when a visitor has not chosen one yet.
     */
    public string $default_locale;

    public static function group(): string
    {
        return 'localization';
    }
}
