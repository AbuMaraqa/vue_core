<?php

namespace Database\Seeders;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Enums\TaxScope;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Accounting\Settings\AccountingSettings;
use App\Modules\Accounting\Support\ChartOfAccountsTemplate;
use Illuminate\Database\Seeder;

/**
 * The starting accounting data: currencies, the chart of accounts with its
 * default accounts, the usual taxes and the current fiscal year.
 *
 * Safe to run again: existing records are kept, only missing ones are added.
 */
class AccountingSeeder extends Seeder
{
    public function run(AccountingSettings $settings): void
    {
        foreach (ChartOfAccountsTemplate::currencies() as [$code, $arabic, $english, $symbol, $decimals]) {
            Currency::query()->firstOrCreate(['code' => $code], [
                'name' => ['ar' => $arabic, 'en' => $english],
                'symbol' => $symbol,
                'decimal_places' => $decimals,
            ]);
        }

        $roles = [];
        $this->createAccounts(ChartOfAccountsTemplate::accounts(), null, $roles);

        $settings->default_accounts = [...$roles, ...$settings->default_accounts];
        $settings->save();

        $this->createTaxes();
        $this->createFiscalYear();
    }

    /**
     * @param  array<mixed>  $accounts
     * @param  array<string, int>  $roles
     */
    protected function createAccounts(array $accounts, ?Account $parent, array &$roles): void
    {
        foreach ($accounts as [$code, $arabic, $english, $subtype, $childrenOrRole]) {
            /** @var AccountSubtype $subtype */
            $isGroup = is_array($childrenOrRole);

            $account = Account::query()->firstOrCreate(['code' => $code], [
                'parent_id' => $parent?->id,
                'name' => ['ar' => $arabic, 'en' => $english],
                'type' => $subtype->type(),
                'subtype' => $subtype,
                'is_group' => $isGroup,
            ]);

            if ($childrenOrRole instanceof AccountRole) {
                $roles[$childrenOrRole->value] = $account->id;
            }

            if ($isGroup) {
                $this->createAccounts($childrenOrRole, $account, $roles);
            }
        }
    }

    protected function createTaxes(): void
    {
        if (Tax::query()->exists()) {
            return;
        }

        $sales = Account::query()->where('code', '2103')->value('id');
        $purchases = Account::query()->where('code', '1107')->value('id');

        foreach ([
            [['ar' => 'ضريبة المبيعات 16%', 'en' => 'VAT 16%'], '16'],
            [['ar' => 'خاضع بنسبة صفر', 'en' => 'Zero rated'], '0'],
            [['ar' => 'معفى', 'en' => 'Exempt'], '0'],
        ] as [$name, $rate]) {
            Tax::query()->create([
                'name' => $name,
                'rate' => $rate,
                'scope' => TaxScope::Both,
                'sales_account_id' => $sales,
                'purchase_account_id' => $purchases,
            ]);
        }
    }

    protected function createFiscalYear(): void
    {
        if (FiscalYear::query()->containing(now())->exists()) {
            return;
        }

        FiscalYear::query()->create([
            'name' => (string) now()->year,
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
        ]);
    }
}
