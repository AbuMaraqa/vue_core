<?php

namespace Database\Seeders;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Settings\AccountingSettings;
use App\Modules\Assets\Enums\DepreciationMethod;
use App\Modules\Assets\Models\AssetCategory;
use App\Modules\Banking\Actions\BankAccounts\SaveBankAccount;
use App\Modules\Banking\Data\BankAccountData;
use App\Modules\Banking\Enums\BankAccountType;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Payroll\Enums\PayCalculation;
use App\Modules\Payroll\Enums\PayComponentType;
use App\Modules\Payroll\Models\PayComponent;
use Illuminate\Database\Seeder;

/**
 * What the operational modules need to be used right away: the main cash
 * box, a warehouse and units, asset categories on the standard accounts and
 * the usual pay components (Jordan's social security rates by default).
 *
 * Run it after AccountingSeeder. Safe to run again: only what is missing is
 * added.
 */
class OperationsSeeder extends Seeder
{
    public function run(AccountingSettings $settings, SaveBankAccount $saveBankAccount): void
    {
        $account = fn (string $code): ?int => Account::query()->where('code', $code)->value('id');

        $cashAccount = $account('110101');
        $baseCurrency = Currency::query()->where('code', $settings->base_currency)->value('id');

        if ($cashAccount !== null && $baseCurrency !== null && ! BankAccount::query()->where('account_id', $cashAccount)->exists()) {
            $saveBankAccount->handle(new BankAccount, new BankAccountData(
                name: 'الصندوق الرئيسي',
                type: BankAccountType::Cash,
                currency_id: $baseCurrency,
                account_id: $cashAccount,
            ));
        }

        Warehouse::query()->firstOrCreate(['code' => 'MAIN'], ['name' => ['ar' => 'المستودع الرئيسي', 'en' => 'Main warehouse']]);

        foreach ([['قطعة', 'Piece', 'pc'], ['كرتونة', 'Box', 'box'], ['كيلوغرام', 'Kilogram', 'kg'], ['ساعة', 'Hour', 'h']] as [$arabic, $english, $symbol]) {
            Unit::query()->firstOrCreate(['symbol' => $symbol], ['name' => ['ar' => $arabic, 'en' => $english]]);
        }

        $categories = [
            ['الأثاث والمعدات', 'Furniture and Equipment', '1201', '120901', 120],
            ['أجهزة الحاسوب', 'Computers', '1202', '120902', 48],
            ['السيارات', 'Vehicles', '1203', '120903', 84],
            ['المباني', 'Buildings', '1204', '120904', 480],
        ];

        foreach ($categories as [$arabic, $english, $assetCode, $accumulatedCode, $life]) {
            if (AssetCategory::query()->where('asset_account_id', $account($assetCode))->exists() || $account($assetCode) === null) {
                continue;
            }

            AssetCategory::query()->create([
                'name' => ['ar' => $arabic, 'en' => $english],
                'asset_account_id' => $account($assetCode),
                'accumulated_depreciation_account_id' => $account($accumulatedCode),
                'depreciation_expense_account_id' => $account('5205'),
                'method' => DepreciationMethod::StraightLine,
                'useful_life_months' => $life,
            ]);
        }

        if (PayComponent::query()->exists()) {
            return;
        }

        $components = [
            ['بدل مواصلات', 'Transport allowance', PayComponentType::Earning, PayCalculation::Fixed, '0', '5201', null],
            ['الضمان الاجتماعي (حصة الموظف)', 'Social security (employee)', PayComponentType::Deduction, PayCalculation::PercentOfBasic, '7.5', '2105', null],
            ['الضمان الاجتماعي (حصة الشركة)', 'Social security (employer)', PayComponentType::EmployerContribution, PayCalculation::PercentOfBasic, '14.25', '5201', '2105'],
            ['ضريبة الدخل', 'Income tax', PayComponentType::Deduction, PayCalculation::Fixed, '0', '2106', null],
            ['استرداد سلفة', 'Advance recovery', PayComponentType::Deduction, PayCalculation::Fixed, '0', '1109', null],
        ];

        foreach ($components as [$arabic, $english, $type, $calculation, $value, $accountCode, $liabilityCode]) {
            if ($account($accountCode) === null) {
                continue;
            }

            PayComponent::query()->create([
                'name' => ['ar' => $arabic, 'en' => $english],
                'type' => $type,
                'calculation' => $calculation,
                'default_value' => $value,
                'account_id' => $account($accountCode),
                'liability_account_id' => $liabilityCode ? $account($liabilityCode) : null,
            ]);
        }
    }
}
