<?php

namespace App\Modules\Accounting\Enums;

/**
 * The accounts the system posts to automatically, chosen once in the
 * accounting settings: e.g. every sales invoice debits the Receivable account.
 */
enum AccountRole: string
{
    case Receivable = 'receivable';
    case Payable = 'payable';
    case Sales = 'sales';
    case Purchases = 'purchases';
    case Inventory = 'inventory';
    case CostOfSales = 'cost_of_sales';
    case InventoryAdjustment = 'inventory_adjustment';
    case ChequesInHand = 'cheques_in_hand';
    case ChequesUnderCollection = 'cheques_under_collection';
    case ChequesPayable = 'cheques_payable';
    case RetainedEarnings = 'retained_earnings';
    case OpeningBalanceEquity = 'opening_balance_equity';
    case ExchangeGain = 'exchange_gain';
    case ExchangeLoss = 'exchange_loss';
    case BankCharges = 'bank_charges';
    case SalariesExpense = 'salaries_expense';
    case SalariesPayable = 'salaries_payable';
    case DepreciationExpense = 'depreciation_expense';
    case AssetDisposalGain = 'asset_disposal_gain';
    case AssetDisposalLoss = 'asset_disposal_loss';
    case WorkInProgress = 'work_in_progress';
    case AppliedProductionCosts = 'applied_production_costs';

    public function label(): string
    {
        return __(str($this->name)->headline()->value());
    }
}
