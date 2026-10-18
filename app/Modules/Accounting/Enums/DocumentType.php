<?php

namespace App\Modules\Accounting\Enums;

/**
 * Every numbered document of the system, with its default number prefix.
 * Numbers restart each year: `INV-2026-00001`.
 */
enum DocumentType: string
{
    case JournalEntry = 'journal_entry';
    case SalesQuotation = 'sales_quotation';
    case SalesInvoice = 'sales_invoice';
    case SalesCreditNote = 'sales_credit_note';
    case PurchaseBill = 'purchase_bill';
    case PurchaseDebitNote = 'purchase_debit_note';
    case Receipt = 'receipt';
    case Payment = 'payment';
    case Expense = 'expense';
    case Deposit = 'deposit';
    case BankTransfer = 'bank_transfer';
    case StockAdjustment = 'stock_adjustment';
    case StockTransfer = 'stock_transfer';
    case Asset = 'asset';
    case DepreciationRun = 'depreciation_run';
    case PayrollRun = 'payroll_run';
    case ProductionOrder = 'production_order';

    public function defaultPrefix(): string
    {
        return match ($this) {
            self::JournalEntry => 'JE',
            self::SalesQuotation => 'QT',
            self::SalesInvoice => 'INV',
            self::SalesCreditNote => 'CN',
            self::PurchaseBill => 'BILL',
            self::PurchaseDebitNote => 'DN',
            self::Receipt => 'RCT',
            self::Payment => 'PAY',
            self::Expense => 'EXP',
            self::Deposit => 'DEP',
            self::BankTransfer => 'TRF',
            self::StockAdjustment => 'ADJ',
            self::StockTransfer => 'STR',
            self::Asset => 'FA',
            self::DepreciationRun => 'DPR',
            self::PayrollRun => 'PR',
            self::ProductionOrder => 'PRD',
        };
    }

    public function label(): string
    {
        return __(str($this->name)->headline()->value());
    }
}
