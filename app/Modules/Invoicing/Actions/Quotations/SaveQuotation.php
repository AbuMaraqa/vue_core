<?php

namespace App\Modules\Invoicing\Actions\Quotations;

use App\Modules\Accounting\Enums\DocumentType;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Accounting\Support\DocumentNumbers;
use App\Modules\Inventory\Models\Item;
use App\Modules\Invoicing\Data\QuotationData;
use App\Modules\Invoicing\Enums\QuotationStatus;
use App\Modules\Invoicing\Models\Quotation;
use App\Modules\Invoicing\Support\InvoiceCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Create or update a quotation, working out its amounts exactly like an
 * invoice would, so the invoice it becomes has the same total.
 */
class SaveQuotation
{
    /**
     * How long a quotation is valid when no date is given.
     */
    public const int DEFAULT_VALIDITY_DAYS = 30;

    public function __construct(protected DocumentNumbers $numbers) {}

    public function handle(Quotation $quotation, QuotationData $data): Quotation
    {
        if ($quotation->exists && ! $quotation->isEditable()) {
            throw new AccountingException(__('Only quotations still waiting for the customer can be changed.'));
        }

        return DB::transaction(function () use ($quotation, $data): Quotation {
            $currency = Currency::query()->findOrFail($data->currency_id);
            $items = Item::query()->findMany(array_filter(array_column($data->lines, 'item_id')))->keyBy('id');
            $taxes = Tax::query()->findMany(array_filter(array_column($data->lines, 'tax_id')))->keyBy('id');

            $lines = [];

            foreach ($data->lines as $position => $line) {
                $item = isset($line['item_id']) ? $items[$line['item_id']] ?? null : null;
                $tax = isset($line['tax_id']) ? $taxes[$line['tax_id']] ?? null : null;

                $lines[] = [
                    'item_id' => $item?->id,
                    'description' => $line['description'] ?? $item?->translate('name'),
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

            $quotation->fill([
                'contact_id' => $data->contact_id,
                'date' => $data->date,
                'valid_until' => $data->valid_until ?? Carbon::parse($data->date)->addDays(self::DEFAULT_VALIDITY_DAYS)->toDateString(),
                'reference' => $data->reference,
                'currency_id' => $currency->id,
                'prices_include_tax' => $data->prices_include_tax,
                'notes' => $data->notes,
                ...InvoiceCalculator::totals($lines),
            ]);

            if (! $quotation->exists) {
                $userId = Auth::id();

                $quotation->number = $this->numbers->next(DocumentType::SalesQuotation, $data->date);
                $quotation->status = QuotationStatus::Draft;
                $quotation->created_by = is_int($userId) ? $userId : null;
            }

            $quotation->save();
            $quotation->lines()->delete();
            $quotation->lines()->createMany($lines);

            return $quotation->load('lines');
        });
    }
}
