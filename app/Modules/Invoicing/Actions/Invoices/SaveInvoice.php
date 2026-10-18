<?php

namespace App\Modules\Invoicing\Actions\Invoices;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inventory\Models\Item;
use App\Modules\Invoicing\Data\InvoiceData;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Support\InvoiceCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Create or update a draft invoice: resolve each line's account, work out
 * the amounts and save the lines. Nothing reaches the books until it is
 * posted.
 */
class SaveInvoice
{
    public function __construct(
        protected Currencies $currencies,
        protected DefaultAccounts $defaults,
    ) {}

    public function handle(Invoice $invoice, InvoiceData $data): Invoice
    {
        if ($invoice->exists && ! $invoice->isDraft()) {
            throw new AccountingException(__('Only drafts can be changed.'));
        }

        return DB::transaction(function () use ($invoice, $data): Invoice {
            $contact = Contact::query()->findOrFail($data->contact_id);
            $currency = Currency::query()->findOrFail($data->currency_id);
            $items = Item::query()->with('category')->findMany(array_filter(array_column($data->lines, 'item_id')))->keyBy('id');
            $taxes = Tax::query()->findMany(array_filter(array_column($data->lines, 'tax_id')))->keyBy('id');

            $lines = [];

            foreach ($data->lines as $position => $line) {
                $item = isset($line['item_id']) ? $items[$line['item_id']] ?? null : null;
                $tax = ! $data->is_opening && isset($line['tax_id']) ? $taxes[$line['tax_id']] ?? null : null;

                $lines[] = [
                    'item_id' => $item?->id,
                    'description' => $line['description'] ?? $item?->translate('name'),
                    'account_id' => $this->accountFor($data, $line, $item),
                    'cost_center_id' => $line['cost_center_id'] ?? $data->cost_center_id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_percent' => $line['discount_percent'] ?? '0',
                    'tax_id' => $tax?->id,
                    'tax_rate' => $tax !== null ? (string) $tax->rate : '0',
                    'sort' => $position,
                    ...InvoiceCalculator::line(
                        $line['quantity'],
                        $line['unit_price'],
                        $line['discount_percent'] ?? null,
                        $tax?->rate,
                        $data->prices_include_tax,
                        $currency->decimal_places,
                    ),
                ];
            }

            $userId = Auth::id();

            $invoice->fill([
                'type' => $data->type,
                'contact_id' => $contact->id,
                'date' => $data->date,
                'due_date' => $data->due_date ?? Carbon::parse($data->date)->addDays($contact->payment_terms_days)->toDateString(),
                'reference' => $data->reference,
                'currency_id' => $currency->id,
                'exchange_rate' => $this->currencies->isBase($currency)
                    ? '1'
                    : (filled($data->exchange_rate) ? (string) $data->exchange_rate : $this->currencies->rate($currency, $data->date)),
                'warehouse_id' => $data->warehouse_id,
                'cost_center_id' => $data->cost_center_id,
                'original_invoice_id' => $data->original_invoice_id,
                'prices_include_tax' => $data->prices_include_tax,
                'is_opening' => $data->is_opening,
                'notes' => $data->notes,
                ...InvoiceCalculator::totals($lines),
            ]);

            if (! $invoice->exists) {
                $invoice->status = DocumentStatus::Draft;
                $invoice->created_by = is_int($userId) ? $userId : null;
            }

            $invoice->save();
            $invoice->lines()->delete();
            $invoice->lines()->createMany($lines);

            return $invoice->load('lines');
        });
    }

    /**
     * The account of a line: the one chosen, else the item's, else none.
     * Opening invoices post against opening balance equity.
     *
     * @param  array<string, mixed>  $line
     */
    protected function accountFor(InvoiceData $data, array $line, ?Item $item): int
    {
        if ($data->is_opening) {
            return $this->defaults->id(AccountRole::OpeningBalanceEquity);
        }

        if (filled($line['account_id'] ?? null)) {
            return (int) $line['account_id'];
        }

        if ($item === null) {
            throw new AccountingException(__('Choose an item or an account for every line.'));
        }

        return $data->type->isSales() ? $item->salesAccountId() : $item->purchaseAccountId();
    }
}
