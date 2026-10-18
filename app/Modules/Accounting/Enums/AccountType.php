<?php

namespace App\Modules\Accounting\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * The five classes of accounts of double-entry bookkeeping.
 */
enum AccountType: string
{
    use EnumOptions;

    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Asset => __('Assets'),
            self::Liability => __('Liabilities'),
            self::Equity => __('Equity'),
            self::Revenue => __('Revenue'),
            self::Expense => __('Expenses'),
        };
    }

    /**
     * Assets and expenses grow with debits; the others with credits.
     */
    public function isDebitNormal(): bool
    {
        return in_array($this, [self::Asset, self::Expense], true);
    }

    /**
     * Revenue and expenses are closed into retained earnings at year end.
     */
    public function isIncomeStatement(): bool
    {
        return in_array($this, [self::Revenue, self::Expense], true);
    }
}
