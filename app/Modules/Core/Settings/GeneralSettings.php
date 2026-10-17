<?php

namespace App\Modules\Core\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;

    public ?string $site_description;

    public ?string $contact_email;

    public ?string $contact_phone;

    public static function group(): string
    {
        return 'general';
    }
}
