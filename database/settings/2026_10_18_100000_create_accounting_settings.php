<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('accounting.base_currency', 'JOD');
        $this->migrator->add('accounting.lock_date', null);
        $this->migrator->add('accounting.default_accounts', []);
        $this->migrator->add('accounting.number_prefixes', []);
        $this->migrator->add('accounting.company_tax_number', null);
        $this->migrator->add('accounting.company_address', null);
        $this->migrator->add('accounting.prices_include_tax', false);
        $this->migrator->add('accounting.allow_negative_stock', false);
    }
};
