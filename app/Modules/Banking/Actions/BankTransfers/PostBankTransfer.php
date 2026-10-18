<?php

namespace App\Modules\Banking\Actions\BankTransfers;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Enums\DocumentType;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Accounting\Support\DocumentNumbers;
use App\Modules\Banking\Data\BankTransferData;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\BankTransfer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Move money between two of the company's accounts:
 *
 *   Dr receiving account, Cr sending account
 *
 * In one currency the entry is in that currency. Between the base currency
 * and a foreign one, the entry is in the foreign currency at the rate the
 * two amounts imply, so both accounts get exactly what was entered. Two
 * foreign currencies go through the base currency in two transfers.
 *
 * Fees of the sending bank are a second entry: Dr bank charges, Cr sender.
 */
class PostBankTransfer
{
    public function __construct(
        protected Ledger $ledger,
        protected Currencies $currencies,
        protected DocumentNumbers $numbers,
        protected DefaultAccounts $defaults,
    ) {}

    public function handle(BankTransferData $data): BankTransfer
    {
        return DB::transaction(function () use ($data): BankTransfer {
            $this->ledger->ensureDateIsOpen($data->date);

            $from = BankAccount::query()->with(['currency', 'account'])->findOrFail($data->from_bank_account_id);
            $to = BankAccount::query()->with(['currency', 'account'])->findOrFail($data->to_bank_account_id);
            $sameCurrency = $from->currency_id === $to->currency_id;

            if (! $sameCurrency && ! $this->currencies->isBase($from->currency_id) && ! $this->currencies->isBase($to->currency_id)) {
                throw new AccountingException(__('Transfer between two foreign currencies through a base-currency account.'));
            }

            $amount = Decimal::round($data->amount);
            $toAmount = $sameCurrency ? $amount : Decimal::round($data->to_amount ?? '0');

            if (! Decimal::isPositive($toAmount)) {
                throw new AccountingException(__('Enter the amount received in :currency.', ['currency' => $to->currency->code]));
            }

            $userId = Auth::id();
            $transfer = BankTransfer::query()->create([
                'number' => $this->numbers->next(DocumentType::BankTransfer, $data->date),
                'date' => $data->date,
                'from_bank_account_id' => $from->id,
                'to_bank_account_id' => $to->id,
                'amount' => $amount,
                'to_amount' => $toAmount,
                'fees' => Decimal::round($data->fees ?? '0'),
                'reference' => $data->reference,
                'description' => $data->description,
                'status' => DocumentStatus::Posted,
                'created_by' => is_int($userId) ? $userId : null,
            ]);

            $description = $data->description ?? __('Transfer :number', ['number' => $transfer->number]);

            [$currency, $rate, $entryAmount] = match (true) {
                $sameCurrency => [$from->currency, $this->rate($from, $data), $amount],
                $this->currencies->isBase($from->currency_id) => [$to->currency, Decimal::divide($amount, $toAmount, 8), $toAmount],
                default => [$from->currency, Decimal::divide($toAmount, $amount, 8), $amount],
            };

            JournalEntryBuilder::make($data->date, $description)
                ->currency($currency, $rate)
                ->reference($transfer->number)
                ->source($transfer)
                ->debit($to->account_id, $entryAmount)
                ->credit($from->account_id, $entryAmount)
                ->post();

            if (Decimal::isPositive($transfer->fees)) {
                JournalEntryBuilder::make($data->date, __('Fees of transfer :number', ['number' => $transfer->number]))
                    ->currency($from->currency, $this->rate($from, $data))
                    ->reference($transfer->number)
                    ->source($transfer)
                    ->debit($this->defaults->id(AccountRole::BankCharges), $transfer->fees)
                    ->credit($from->account_id, $transfer->fees)
                    ->post();
            }

            return $transfer;
        });
    }

    /**
     * The rate of the sending account's currency: 1 for the base currency,
     * the entered rate, or the rate of the day.
     */
    protected function rate(BankAccount $from, BankTransferData $data): string
    {
        if ($this->currencies->isBase($from->currency_id)) {
            return '1';
        }

        return filled($data->exchange_rate) ? (string) $data->exchange_rate : $this->currencies->rate($from->currency_id, $data->date);
    }
}
