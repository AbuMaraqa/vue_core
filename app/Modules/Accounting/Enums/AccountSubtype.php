<?php

namespace App\Modules\Accounting\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * A finer classification of accounts. It places each account in the right
 * section of the balance sheet, income statement and cash flow statement.
 */
enum AccountSubtype: string
{
    use EnumOptions;

    case Cash = 'cash';
    case Bank = 'bank';
    case Receivable = 'receivable';
    case ChequesReceivable = 'cheques_receivable';
    case Inventory = 'inventory';
    case OtherCurrentAsset = 'other_current_asset';
    case FixedAsset = 'fixed_asset';
    case AccumulatedDepreciation = 'accumulated_depreciation';
    case OtherAsset = 'other_asset';
    case Payable = 'payable';
    case ChequesPayable = 'cheques_payable';
    case TaxPayable = 'tax_payable';
    case OtherCurrentLiability = 'other_current_liability';
    case LongTermLiability = 'long_term_liability';
    case Equity = 'equity';
    case RetainedEarnings = 'retained_earnings';
    case OperatingRevenue = 'operating_revenue';
    case OtherRevenue = 'other_revenue';
    case CostOfSales = 'cost_of_sales';
    case OperatingExpense = 'operating_expense';
    case OtherExpense = 'other_expense';

    public function type(): AccountType
    {
        return match ($this) {
            self::Cash, self::Bank, self::Receivable, self::ChequesReceivable, self::Inventory,
            self::OtherCurrentAsset, self::FixedAsset, self::AccumulatedDepreciation, self::OtherAsset => AccountType::Asset,
            self::Payable, self::ChequesPayable, self::TaxPayable, self::OtherCurrentLiability, self::LongTermLiability => AccountType::Liability,
            self::Equity, self::RetainedEarnings => AccountType::Equity,
            self::OperatingRevenue, self::OtherRevenue => AccountType::Revenue,
            self::CostOfSales, self::OperatingExpense, self::OtherExpense => AccountType::Expense,
        };
    }

    public function label(): string
    {
        return __(str($this->name)->headline()->value());
    }

    /**
     * Cash and bank accounts: the "cash" of the cash flow statement.
     */
    public function isCashEquivalent(): bool
    {
        return in_array($this, [self::Cash, self::Bank], true);
    }

    /**
     * Assets and liabilities that change with day-to-day operations, shown as
     * working capital changes in the cash flow statement.
     */
    public function isWorkingCapital(): bool
    {
        return in_array($this, [
            self::Receivable, self::ChequesReceivable, self::Inventory, self::OtherCurrentAsset,
            self::Payable, self::ChequesPayable, self::TaxPayable, self::OtherCurrentLiability,
        ], true);
    }

    /**
     * The subtypes allowed under the given account type.
     *
     * @return list<self>
     */
    public static function forType(AccountType $type): array
    {
        return array_values(array_filter(self::cases(), fn (self $subtype): bool => $subtype->type() === $type));
    }
}
