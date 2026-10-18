<?php

use App\Modules\Foundation\Printing\PaperSize;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('print.paper_size', PaperSize::A4->value);
        $this->migrator->add('print.show_logo', true);
        $this->migrator->add('print.show_printed_by', true);
        $this->migrator->add('print.footer_note', null);
    }
};
