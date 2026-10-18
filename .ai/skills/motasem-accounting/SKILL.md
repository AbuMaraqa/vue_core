---
name: motasem-accounting
description: 'The accounting/ERP modules of this project (Accounting, Contacts, Inventory, Banking with cheques, Invoicing, Assets, Payroll): the posting engine, document lifecycle, money and currency rules, stock costing, the cheque lifecycle, allocations and FX, depreciation, payroll, reports, and how to add a new financial document safely. Activate for ANY work on accounts, journal entries, invoices, bills, credit/debit notes, payments, receipts, cheques, banks, cash, transfers, vouchers, reconciliation, stock, items, warehouses, fixed assets, depreciation, payroll, salaries, taxes, currencies, fiscal years or financial reports (محاسبة، قيود، فواتير، مشتريات، مبيعات، سندات قبض وصرف، شيكات، بنوك، صندوق، مخزون، مستودعات، أصول ثابتة، استهلاك، رواتب، ضرائب، عملات، تقارير مالية).'
---

# Accounting Modules

A double-entry accounting system split into modules. Read **motasem-architecture** first.

## The modules and how they depend on each other

| Module       | Holds                                                                                                                                   | Depends on                               |
| ------------ | --------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------- |
| `Accounting` | chart of accounts, currencies and rates, fiscal years, taxes, cost centers, budgets, journal entries, posting engine, financial reports | Core, Foundation                         |
| `Contacts`   | customers and vendors (one table), their balances and statements                                                                        | Accounting                               |
| `Inventory`  | items, units, categories, warehouses, stock ledger, adjustments, transfers, stock reports                                               | Accounting                               |
| `Banking`    | bank and cash accounts, transfers, payment/receipt vouchers, reconciliation, the cheque register                                        | Accounting, Contacts                     |
| `Invoicing`  | sales invoices, credit notes, purchase bills, debit notes, receipts, payments, allocations, aging, sales analysis, invoice PDF          | Accounting, Contacts, Inventory, Banking |
| `Assets`     | fixed asset register, categories, monthly depreciation runs, disposal                                                                   | Accounting                               |
| `Payroll`    | employees, pay components, monthly payroll runs (prepare → post → pay)                                                                  | Accounting, Banking                      |
| `Production` | bills of materials (recipes), production orders (draft → in progress → completed), production report                                 | Accounting, Contacts, Inventory          |

- A module never reaches "up" the table. Cross-module reactions go through events: `Banking\Events\ChequeVoided` is handled by `Invoicing\Listeners\FreeInvoicesOfVoidedCheque`.
- All of them add their menu to the sidebar section `accounting` (sort 30), as `SidebarGroup`s ordered by `sort`.
- Migrations: `database/migrations/2026_10_18_1000xx_*`. Seeders: `AccountingSeeder` (chart, currencies, taxes, fiscal year, default accounts) then `OperationsSeeder` (cash box, warehouse, units, asset categories, pay components). Both are idempotent.

## The golden rule: everything goes through the ledger

Every financial effect is a journal entry built with `JournalEntryBuilder` and posted by `Ledger::post()`:

```php
JournalEntryBuilder::make($date, __('Sales invoice :number', ['number' => $number]))
    ->currency($currency, $rate)          // omit for the base currency
    ->reference($number)
    ->source($document)                   // the document the entry belongs to
    ->debit($receivableId, $total, partner: $contact)
    ->credit($salesId, $subtotal, costCenterId: $costCenterId)
    ->credit($taxAccountId, $tax)
    ->post();
```

- A negative amount flips to the other side; zero amounts are skipped, so optional lines need no `if`.
- `Ledger::post` refuses: dates on/before the lock date or outside an open fiscal year, fewer than two lines, a line with both or neither side, group or inactive accounts, unbalanced entries, and **a line on an account with its own currency when the entry is in another currency** (foreign bank accounts stay correct in their currency).
- It converts every line to the base currency (`base_debit`/`base_credit`), absorbing rounding in the largest line, and numbers the entry (`JE-2026-00001`).
- Reports read only posted lines (`status` posted or reversed) — never document tables. Year-end closing entries (source `FiscalYear`) are left out of period reports (trial balance, income statement, cash flow, budgets) and kept in the balance sheet and ledgers.
- Undo is always a reversing entry: `Ledger::reverse($entry, $date)`, or `Ledger::reverseEntriesOf($document, $date)` for every entry of a document. `Ledger::openDateFor($date)` gives the document's own date while its period is open, else today. Entries with reconciled bank lines cannot be reversed until the reconciliation is undone.
- Default accounts are chosen by `AccountRole` in the accounting settings; resolve them with `DefaultAccounts::id(AccountRole::Receivable)`. Missing ones raise a clear `AccountingException`.
- `AccountingException` renders as a toast plus an `accounting` form error (web) or 422 (JSON). Throw it for every business rule violation; tests assert `assertSessionHasErrors('accounting')`.

## Money

- Amounts are `decimal(20,4)` columns, rates `decimal(20,8)`, unit costs `decimal(20,6)`. Models cast them to `decimal:N`, so they are **strings**.
- Do math only with `Accounting\Support\Decimal` (Brick BigDecimal): `Decimal::of`, `round($v, $scale)`, `sum`, `multiply`, `percentOf`, `divide`, `isPositive`... Never use floats.
- Document amounts round to the currency's `decimal_places`; base amounts to 4.
- Dates: the app uses `CarbonImmutable` — type-hint `Carbon\CarbonInterface`, and reassign (`$month = $month->addMonth()`).
- The base currency changes only through `ChangeBaseCurrency` (settings page dialog: new currency + `1 old = rate new`). It re-posts every entry's base amounts, re-expresses exchange rates and dispatches `BaseCurrencyChanged`; **every new table with a base-currency amount or an `exchange_rate` needs its module's `Convert*ToNewBaseCurrency` listener updated** (use `BaseCurrencyConversion::convertColumns()` / `convertExchangeRates()`).

## Documents

Every financial document follows the same shape:

1. `Models/*` with `status` cast to `Accounting\Enums\DocumentStatus` (draft/posted/cancelled) and a `number` from `DocumentNumbers::next(DocumentType::X, $date)` (`INV-2026-00001`, prefixes overridable in settings).
2. An action that validates the business rules, writes the document, moves stock if any, and posts entries with `->source($document)` — all inside `DB::transaction`.
3. A cancel action that reverses with `reverseEntriesOf` / `StockLedger::reverse` and sets `Cancelled`. Posted documents are never edited or deleted.
4. A policy whose `cancel`/`post` abilities check the status, and permissions `<Name> View List|View|Create|...` in `PermissionSeeder`.

To add a new document type: add a `DocumentType` case (with its prefix), follow the steps above, and add tests that check the resulting **account balances** (`AccountBalances::asOf`) — not only the document rows.

## Contacts

- `Contact` is a customer, a vendor or both (`is_customer`, `is_vendor`); its own receivable/payable accounts are optional (`receivableAccountId()` falls back to the default).
- A contact's balance is the posted lines that name it as **partner** on receivable/payable accounts (`ContactBalances`). Always pass `partner: $contact` on those lines. The statement of account is `Reports/ContactStatement`.

## Inventory (weighted average)

- Only `StockLedger` changes stock: `receive()` (optionally with an exact `value`), `issue()` (at average cost; emptying a warehouse takes all its remaining value), `reverse($document)`.
- The value of a `StockLevel` always equals the sum of its movements, which equals what was posted to the inventory accounts. Costs follow the order movements are recorded in.
- Negative stock is refused unless `allow_negative_stock` is on. Services never have stock.
- Item accounts resolve item → category → default role (`inventoryAccountId()`, `costOfSalesAccountId()`, `salesAccountId()`, `purchaseAccountId()`).

## Banking and cheques

- A `BankAccount` owns one postable account (created under the cash/banks group, e.g. `110201`). Foreign-currency accounts set the account's `currency_id`, so the ledger keeps them in that currency. `BankBalances` reads balances in the account's currency.
- `ChequeRegister` runs the cheque lifecycle. In each status the cheque's value sits in one account (in hand → 1104, under collection → 1105, issued → 2102 cheques payable, collected/cleared → the bank, bounced/returned/cancelled → the counter account, endorsed → the endorsee's payable), so each action posts "from old place to new place". `ChequeAction::transitions()` lists what is allowed; `undo()` reverses the last step. Bouncing, returning or cancelling dispatches `ChequeVoided`.
- Reconciliation marks `journal_lines.reconciliation_id`; the cleared total must equal the statement balance.

## Invoicing

- One `invoices` table for `InvoiceType` (sales invoice, credit note, purchase bill, debit note). Each type has its own routes (`admin.sales.invoices.*`, ...) served by one controller; `InvoiceType::fromRoute($request)` reads the type. Authorize **before** checking the route type, so guards return 403.
- `InvoiceCalculator` is the line math (discount, tax-exclusive or tax-inclusive, rounding per line). The frontend mirrors it.
- `PostInvoice` posts the entry in the invoice currency and the stock cost in a second base-currency entry (COGS on sales, cost of returns on credit notes, purchase price variance on debit notes).
- `Allocator` settles invoices with payments or notes (same contact and currency) and posts the realized FX difference to exchange gain/loss. Payments by cheque are undone through the cheque; when it is voided, the listener removes the allocations.
- Opening balances of contacts are opening invoices/bills (`is_opening`) against opening balance equity, so they age and can be paid.

## Assets and payroll

- `DepreciationSchedule::rows($asset)` is the whole schedule (straight line or declining balance, never below salvage, last month takes the remainder). `RunDepreciation` posts what is due up to a month-end (catching up missed months); only the latest run can be cancelled; `DisposeAsset` catches up then posts gain/loss.
- `PreparePayrollRun` builds payslips (basic prorated by days employed; components fixed or % of basic). `PostPayrollRun` accrues (expenses by cost center, net per employee as partner on salaries payable), `PayPayrollRun` pays from a base-currency account, `ReversePayrollRun` steps back one stage.

## Production

- A `BillOfMaterials` is a recipe: materials per batch (with a waste %) and other costs (labour, paint...). An order copies it scaled on the frontend; the server stores only the order's own lines.
- `StartProductionOrder` issues the materials (`ProductionIssue`) at average cost: Dr work in progress (`AccountRole::WorkInProgress`, 1110), Cr inventory.
- `CompleteProductionOrder` takes the real consumption: extra is issued (`ProductionExtraIssue`), the rest returned at its issue cost (`ProductionReturn`); other costs are credited to their accounts (default `AppliedProductionCosts`, 5104); the product is received (`ProductionOutput`) at the full cost, so its unit cost is real.
- `ReverseProductionOrder` steps back one stage (`StockLedger::reverse($order, $date, $types)` + the latest posted entry). `DiscardProductionOrder` deletes a never-started draft, else marks it cancelled.
- The module is optional: leave `ProductionServiceProvider` out of `bootstrap/providers.php` for businesses that do not manufacture.

## Reports

- Each report is a class with `generate(...)` (array for the page) and `export(...)` (a Foundation `Report` for xlsx/csv/pdf).
- Controllers use the `Accounting\Http\Controllers\Concerns\RendersReports` trait: one GET route renders the page, or downloads when `?format=` is given (the export permission is checked then). `reportPeriod()` defaults to the current fiscal year.
- Vue report pages use `components/accounting/ReportShell.vue` (`export-permission`, `section` props) and `useReportFilters`.

## Frontend

- Pages live in `resources/js/pages/admin/{accounting,contacts,inventory,banking,invoicing,assets,payroll}`. Types in `resources/js/types/<module>.ts`.
- Pickers for large lists search the server: `useContactSearch(type)`, `useItemSearch(type)` with `BaseCombobox :search`. `useExchangeRate` fetches the rate of a currency on a date.
- `lib/invoicing-routes.ts` maps a document type's route prefix to its Wayfinder module.
- After adding routes run `php artisan wayfinder:generate --with-form`.

## Tests

- `tests/Feature/Modules/<Module>/`. Use `seedAccounting()` and `account('1103')` (Pest helpers), `travelTo()` a fixed date inside the seeded fiscal year, and build scenarios with figures easy to check by hand.
- Assert balances through `AccountBalances::asOf(...)`, `ContactBalances`, `BankBalances`, `StockLedger::onHand()`.
- `AdminRoutesAuthorizationTest` creates every route's model parameters with their factory: every model used as a route parameter needs a factory.
