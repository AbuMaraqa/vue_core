<?php

namespace App\Modules\Banking\Printing;

use App\Modules\Accounting\Printing\FinancialDocument;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Reports\BankAccountRegister;
use Carbon\CarbonInterface;

/**
 * The printed statement of a bank or cash account for a period: the opening
 * balance, every movement in and out with the running balance, and the
 * closing balance, in the account's currency.
 */
class BankAccountStatementDocument extends FinancialDocument
{
    /**
     * @param  array{opening: string, closing: string, total_in: string, total_out: string, lines: list<array<string, mixed>>, truncated: bool}  $register  The output of {@see BankAccountRegister::generate()}.
     */
    public function __construct(
        protected BankAccount $bankAccount,
        protected array $register,
        protected CarbonInterface $from,
        protected CarbonInterface $to,
    ) {
        $bankAccount->loadMissing(['currency']);
    }

    public function title(): string
    {
        return __('Account statement');
    }

    public function details(): array
    {
        return [
            __('Bank account') => $this->bankAccount->name,
            __('Period') => (string) __(':from to :to', ['from' => self::isolatedDate($this->from), 'to' => self::isolatedDate($this->to)]),
            __('Currency') => $this->bankAccount->currency->code,
            __('Bank') => $this->bankAccount->bank_name,
            __('Branch') => $this->bankAccount->branch,
            __('Account number') => $this->bankAccount->account_number,
            __('IBAN') => $this->bankAccount->iban,
        ];
    }

    /**
     * A date kept left-to-right inside a translated sentence: the details are
     * escaped text, so the Unicode isolate marks stand in for a `dir="ltr"` span.
     */
    protected static function isolatedDate(CarbonInterface $date): string
    {
        return "\u{2066}".self::date($date)."\u{2069}";
    }

    public function filename(): string
    {
        return 'statement-'.$this->bankAccount->id.'-'.self::date($this->from).'-'.self::date($this->to).'.pdf';
    }

    protected function view(): string
    {
        return 'banking::print.statement';
    }

    protected function data(): array
    {
        return [
            'bankAccount' => $this->bankAccount,
            'register' => $this->register,
        ];
    }
}
