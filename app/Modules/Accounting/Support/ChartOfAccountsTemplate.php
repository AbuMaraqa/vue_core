<?php

namespace App\Modules\Accounting\Support;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Enums\AccountSubtype;

/**
 * The starting chart of accounts: a standard, bilingual structure that a new
 * company can adjust. Each entry is [code, Arabic, English, subtype, children
 * or role]. Group accounts have children; postable accounts may take a role.
 */
final class ChartOfAccountsTemplate
{
    /**
     * @return list<array{0: string, 1: string, 2: string, 3: AccountSubtype, 4?: list<mixed>|AccountRole|null}>
     */
    public static function accounts(): array
    {
        return [
            ['1', 'الأصول', 'Assets', AccountSubtype::OtherCurrentAsset, [
                ['11', 'الأصول المتداولة', 'Current Assets', AccountSubtype::OtherCurrentAsset, [
                    ['1101', 'النقدية', 'Cash', AccountSubtype::Cash, [
                        ['110101', 'الصندوق الرئيسي', 'Main Cash Box', AccountSubtype::Cash, null],
                    ]],
                    ['1102', 'البنوك', 'Banks', AccountSubtype::Bank, []],
                    ['1103', 'الذمم المدينة (العملاء)', 'Accounts Receivable', AccountSubtype::Receivable, AccountRole::Receivable],
                    ['1104', 'شيكات برسم التحصيل في الصندوق', 'Cheques in Hand', AccountSubtype::ChequesReceivable, AccountRole::ChequesInHand],
                    ['1105', 'شيكات مودعة برسم التحصيل', 'Cheques Under Collection', AccountSubtype::ChequesReceivable, AccountRole::ChequesUnderCollection],
                    ['1106', 'المخزون', 'Inventory', AccountSubtype::Inventory, AccountRole::Inventory],
                    ['1107', 'ضريبة المدخلات (المشتريات)', 'Input VAT', AccountSubtype::OtherCurrentAsset, null],
                    ['1108', 'مصاريف مدفوعة مقدماً', 'Prepaid Expenses', AccountSubtype::OtherCurrentAsset, null],
                    ['1109', 'سلف الموظفين', 'Employee Advances', AccountSubtype::OtherCurrentAsset, null],
                    ['1110', 'إنتاج تحت التشغيل', 'Work in Progress', AccountSubtype::Inventory, AccountRole::WorkInProgress],
                ]],
                ['12', 'الأصول الثابتة', 'Fixed Assets', AccountSubtype::FixedAsset, [
                    ['1201', 'الأثاث والمعدات', 'Furniture and Equipment', AccountSubtype::FixedAsset, null],
                    ['1202', 'أجهزة الحاسوب', 'Computers', AccountSubtype::FixedAsset, null],
                    ['1203', 'السيارات', 'Vehicles', AccountSubtype::FixedAsset, null],
                    ['1204', 'المباني', 'Buildings', AccountSubtype::FixedAsset, null],
                    ['1209', 'مجمع الاستهلاك', 'Accumulated Depreciation', AccountSubtype::AccumulatedDepreciation, [
                        ['120901', 'مجمع استهلاك الأثاث والمعدات', 'Accumulated Depreciation - Furniture', AccountSubtype::AccumulatedDepreciation, null],
                        ['120902', 'مجمع استهلاك أجهزة الحاسوب', 'Accumulated Depreciation - Computers', AccountSubtype::AccumulatedDepreciation, null],
                        ['120903', 'مجمع استهلاك السيارات', 'Accumulated Depreciation - Vehicles', AccountSubtype::AccumulatedDepreciation, null],
                        ['120904', 'مجمع استهلاك المباني', 'Accumulated Depreciation - Buildings', AccountSubtype::AccumulatedDepreciation, null],
                    ]],
                ]],
            ]],
            ['2', 'الخصوم', 'Liabilities', AccountSubtype::OtherCurrentLiability, [
                ['21', 'الخصوم المتداولة', 'Current Liabilities', AccountSubtype::OtherCurrentLiability, [
                    ['2101', 'الذمم الدائنة (الموردين)', 'Accounts Payable', AccountSubtype::Payable, AccountRole::Payable],
                    ['2102', 'شيكات آجلة الدفع', 'Cheques Payable', AccountSubtype::ChequesPayable, AccountRole::ChequesPayable],
                    ['2103', 'ضريبة المخرجات (المبيعات)', 'Output VAT', AccountSubtype::TaxPayable, null],
                    ['2104', 'رواتب مستحقة', 'Salaries Payable', AccountSubtype::OtherCurrentLiability, AccountRole::SalariesPayable],
                    ['2105', 'الضمان الاجتماعي المستحق', 'Social Security Payable', AccountSubtype::OtherCurrentLiability, null],
                    ['2106', 'ضريبة الدخل المقتطعة', 'Income Tax Withheld', AccountSubtype::TaxPayable, null],
                    ['2107', 'مصاريف مستحقة', 'Accrued Expenses', AccountSubtype::OtherCurrentLiability, null],
                ]],
                ['22', 'الخصوم طويلة الأجل', 'Long-term Liabilities', AccountSubtype::LongTermLiability, [
                    ['2201', 'القروض', 'Loans', AccountSubtype::LongTermLiability, null],
                ]],
            ]],
            ['3', 'حقوق الملكية', 'Equity', AccountSubtype::Equity, [
                ['3101', 'رأس المال', 'Capital', AccountSubtype::Equity, null],
                ['3102', 'الأرباح المحتجزة', 'Retained Earnings', AccountSubtype::RetainedEarnings, AccountRole::RetainedEarnings],
                ['3103', 'أرصدة افتتاحية', 'Opening Balance Equity', AccountSubtype::Equity, AccountRole::OpeningBalanceEquity],
                ['3104', 'المسحوبات الشخصية', 'Owner Drawings', AccountSubtype::Equity, null],
            ]],
            ['4', 'الإيرادات', 'Revenue', AccountSubtype::OperatingRevenue, [
                ['41', 'إيرادات النشاط', 'Operating Revenue', AccountSubtype::OperatingRevenue, [
                    ['4101', 'إيرادات المبيعات', 'Sales Revenue', AccountSubtype::OperatingRevenue, AccountRole::Sales],
                    ['4102', 'إيرادات الخدمات', 'Service Revenue', AccountSubtype::OperatingRevenue, null],
                ]],
                ['42', 'إيرادات أخرى', 'Other Revenue', AccountSubtype::OtherRevenue, [
                    ['4201', 'إيرادات متنوعة', 'Other Income', AccountSubtype::OtherRevenue, null],
                    ['4202', 'أرباح فروقات العملة', 'Exchange Gains', AccountSubtype::OtherRevenue, AccountRole::ExchangeGain],
                    ['4203', 'أرباح بيع الأصول', 'Gain on Asset Disposal', AccountSubtype::OtherRevenue, AccountRole::AssetDisposalGain],
                ]],
            ]],
            ['5', 'المصاريف', 'Expenses', AccountSubtype::OperatingExpense, [
                ['51', 'تكلفة المبيعات', 'Cost of Sales', AccountSubtype::CostOfSales, [
                    ['5101', 'تكلفة البضاعة المباعة', 'Cost of Goods Sold', AccountSubtype::CostOfSales, AccountRole::CostOfSales],
                    ['5102', 'فروقات جرد المخزون', 'Inventory Adjustments', AccountSubtype::CostOfSales, AccountRole::InventoryAdjustment],
                    ['5103', 'المشتريات', 'Purchases', AccountSubtype::CostOfSales, AccountRole::Purchases],
                    ['5104', 'تكاليف الإنتاج المحمّلة', 'Applied Production Costs', AccountSubtype::CostOfSales, AccountRole::AppliedProductionCosts],
                ]],
                ['52', 'المصاريف التشغيلية', 'Operating Expenses', AccountSubtype::OperatingExpense, [
                    ['5201', 'الرواتب والأجور', 'Salaries and Wages', AccountSubtype::OperatingExpense, AccountRole::SalariesExpense],
                    ['5202', 'حصة الشركة من الضمان الاجتماعي', 'Employer Social Security', AccountSubtype::OperatingExpense, null],
                    ['5203', 'الإيجارات', 'Rent', AccountSubtype::OperatingExpense, null],
                    ['5204', 'الكهرباء والمياه', 'Utilities', AccountSubtype::OperatingExpense, null],
                    ['5205', 'مصروف الاستهلاك', 'Depreciation Expense', AccountSubtype::OperatingExpense, AccountRole::DepreciationExpense],
                    ['5206', 'عمولات بنكية', 'Bank Charges', AccountSubtype::OperatingExpense, AccountRole::BankCharges],
                    ['5207', 'قرطاسية ولوازم مكتبية', 'Office Supplies', AccountSubtype::OperatingExpense, null],
                    ['5208', 'الدعاية والإعلان', 'Marketing', AccountSubtype::OperatingExpense, null],
                    ['5209', 'الصيانة', 'Maintenance', AccountSubtype::OperatingExpense, null],
                    ['5210', 'المواصلات', 'Transportation', AccountSubtype::OperatingExpense, null],
                    ['5211', 'الاتصالات والإنترنت', 'Telecommunications', AccountSubtype::OperatingExpense, null],
                    ['5212', 'أتعاب مهنية', 'Professional Fees', AccountSubtype::OperatingExpense, null],
                    ['5299', 'مصاريف متنوعة', 'Other Expenses', AccountSubtype::OperatingExpense, null],
                ]],
                ['53', 'مصاريف أخرى', 'Other Expenses', AccountSubtype::OtherExpense, [
                    ['5301', 'خسائر فروقات العملة', 'Exchange Losses', AccountSubtype::OtherExpense, AccountRole::ExchangeLoss],
                    ['5302', 'خسائر بيع الأصول', 'Loss on Asset Disposal', AccountSubtype::OtherExpense, AccountRole::AssetDisposalLoss],
                ]],
            ]],
        ];
    }

    /**
     * The starting currencies: [code, Arabic, English, symbol, decimals].
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: int}>
     */
    public static function currencies(): array
    {
        return [
            ['JOD', 'دينار أردني', 'Jordanian Dinar', 'د.أ', 3],
            ['USD', 'دولار أمريكي', 'US Dollar', '$', 2],
            ['EUR', 'يورو', 'Euro', '€', 2],
            ['ILS', 'شيكل', 'Israeli New Shekel', '₪', 2],
            ['SAR', 'ريال سعودي', 'Saudi Riyal', 'ر.س', 2],
            ['AED', 'درهم إماراتي', 'UAE Dirham', 'د.إ', 2],
            ['EGP', 'جنيه مصري', 'Egyptian Pound', 'ج.م', 2],
            ['KWD', 'دينار كويتي', 'Kuwaiti Dinar', 'د.ك', 3],
            ['GBP', 'جنيه إسترليني', 'Pound Sterling', '£', 2],
        ];
    }
}
