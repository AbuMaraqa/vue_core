<?php

use App\Modules\Core\Enums\FontFamily;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('appearance.font_family', FontFamily::IbmPlexSansArabic->value);
        $this->migrator->add('appearance.primary_color', '#465fff');
    }
};
