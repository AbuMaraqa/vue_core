<?php

namespace App\Modules\Banking\Actions\BankVouchers;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DocumentNumbers;
use App\Modules\Banking\Data\BankVoucherData;
use App\Modules\Banking\Enums\VoucherDirection;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\BankVoucher;
use App\Modules\Contacts\Models\Contact;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Record and post a voucher in the bank account's currency:
 *
 *   payment: Dr each account (net), Dr input tax, Cr bank (total)
 *   receipt: Dr bank (total), Cr each account (net), Cr output tax
 *
 * Tax is worked out on each line and rounded to the currency's decimals.
 * The contact, when chosen, is the partner of the lines, so the voucher
 * shows on its statement when a line hits its receivable or payable.
 */
class PostBankVoucher
{
    public function __construct(
        protected Ledger $ledger,
        protected Currencies $currencies,
        protected DocumentNumbers $numbers,
    ) {}

    public function handle(BankVoucherData $data): BankVoucher
    {
        return DB::transaction(function () use ($data): BankVoucher {
            $this->ledger->ensureDateIsOpen($data->date);

            $bank = BankAccount::query()->with('currency')->findOrFail($data->bank_account_id);
            $contact = $data->contact_id !== null ? Contact::query()->findOrFail($data->contact_id) : null;
            $decimals = $bank->currency->decimal_places;
            $rate = $this->currencies->isBase($bank->currency_id)
                ? '1'
                : (filled($data->exchange_rate) ? (string) $data->exchange_rate : $this->currencies->rate($bank->currency_id, $data->date));

            $taxes = Tax::query()->findMany(array_filter(array_column($data->lines, 'tax_id')))->keyBy('id');
            $lines = [];

            foreach ($data->lines as $line) {
                $tax = isset($line['tax_id']) ? $taxes[$line['tax_id']] ?? null : null;
                $amount = Decimal::round($line['amount'], $decimals);

                $lines[] = [
                    ...$line,
                    'amount' => $amount,
                    'tax_amount' => $tax ? Decimal::percentOf($amount, (string) $tax->rate, $decimals) : '0',
                    'tax_account_id' => $tax ? $this->taxAccount($tax, $data->direction) : null,
                ];
            }

            $subtotal = Decimal::sum(array_column($lines, 'amount'));
            $taxTotal = Decimal::sum(array_column($lines, 'tax_amount'));
            $userId = Auth::id();

            $voucher = BankVoucher::query()->create([
                'number' => $this->numbers->next($data->direction->documentType(), $data->date),
                'direction' => $data->direction,
                'date' => $data->date,
                'bank_account_id' => $bank->id,
                'contact_id' => $contact?->id,
                'payee' => $data->payee,
                'currency_id' => $bank->currency_id,
                'exchange_rate' => $rate,
                'reference' => $data->reference,
                'description' => $data->description,
                'subtotal' => Decimal::round($subtotal),
                'tax_total' => Decimal::round($taxTotal),
                'total' => Decimal::round($subtotal->plus($taxTotal)),
                'status' => DocumentStatus::Posted,
                'created_by' => is_int($userId) ? $userId : null,
            ]);

            foreach ($lines as $position => $line) {
                $voucher->lines()->create([
                    'account_id' => $line['account_id'],
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                    'tax_id' => $line['tax_id'] ?? null,
                    'description' => $line['description'] ?? null,
                    'amount' => $line['amount'],
                    'tax_amount' => $line['tax_amount'],
                    'sort' => $position,
                ]);
            }

            $this->post($voucher, $bank, $lines, $contact, $subtotal->plus($taxTotal));

            return $voucher;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    protected function post(BankVoucher $voucher, BankAccount $bank, array $lines, ?Contact $contact, BigDecimal $total): void
    {
        $sign = $voucher->direction === VoucherDirection::Out ? 1 : -1;

        $entry = JournalEntryBuilder::make($voucher->date, $voucher->description ?? $voucher->direction->label().' '.$voucher->number)
            ->currency($bank->currency, (string) $voucher->exchange_rate)
            ->reference($voucher->number)
            ->source($voucher);

        foreach ($lines as $line) {
            $entry->debit((int) $line['account_id'], Decimal::of($line['amount'])->multipliedBy($sign), $line['description'] ?? null, $contact, $line['cost_center_id'] ?? null);

            if ($line['tax_account_id'] !== null) {
                $entry->debit((int) $line['tax_account_id'], Decimal::of($line['tax_amount'])->multipliedBy($sign), null, $contact);
            }
        }

        $entry->credit($bank->account_id, $total->multipliedBy($sign))->post();
    }

    /**
     * Tax paid is recoverable (purchase account); tax received is owed
     * (sales account).
     */
    protected function taxAccount(Tax $tax, VoucherDirection $direction): ?int
    {
        if (! Decimal::isPositive((string) $tax->rate)) {
            return null;
        }

        $accountId = $direction === VoucherDirection::Out ? $tax->purchase_account_id : $tax->sales_account_id;

        return $accountId ?? throw new AccountingException(__('Choose the accounts of the tax :tax first.', ['tax' => $tax->translate('name')]));
    }
}
