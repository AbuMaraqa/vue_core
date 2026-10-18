<?php

namespace App\Modules\Assets\Printing;

use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Printing\FinancialDocument;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Enums\DepreciationMethod;
use App\Modules\Assets\Models\Asset;

/**
 * The printed fixed asset card: what the asset is, how it is depreciated,
 * its cost, accumulated depreciation and book value, and the depreciation
 * posted so far.
 */
class AssetDocument extends FinancialDocument
{
    protected Currency $baseCurrency;

    public function __construct(protected Asset $asset)
    {
        $asset->loadMissing(['category', 'costCenter', 'fundingAccount', 'depreciations.run']);

        $this->baseCurrency = app(Currencies::class)->base();
    }

    public function title(): string
    {
        return __('Fixed asset card');
    }

    public function number(): ?string
    {
        return $this->asset->number;
    }

    public function status(): ?array
    {
        return match ($this->asset->status) {
            AssetStatus::Disposed => ['label' => $this->asset->status->label(), 'tone' => 'neutral'],
            AssetStatus::Cancelled => ['label' => $this->asset->status->label(), 'tone' => 'danger'],
            AssetStatus::Active, AssetStatus::FullyDepreciated => null,
        };
    }

    public function details(): array
    {
        $asset = $this->asset;

        return [
            __('Name') => $asset->name,
            __('Category') => $asset->category->translate('name'),
            __('Acquisition date') => self::date($asset->acquisition_date),
            __('Status') => $asset->status->label(),
            __('Serial number') => $asset->serial_number,
            __('Location') => $asset->location,
            __('Cost center') => $asset->costCenter?->translate('name'),
            __('Currency') => $this->baseCurrency->code,
        ];
    }

    public function signatures(): array
    {
        return [__('Prepared by'), __('Approved by')];
    }

    protected function view(): string
    {
        return 'assets::print.asset';
    }

    protected function data(): array
    {
        $asset = $this->asset;
        $money = fn (?string $amount): string => self::money($amount, $this->baseCurrency);

        $years = $asset->useful_life_months / 12;

        return [
            'asset' => $asset,
            'baseCurrency' => $this->baseCurrency,
            'postedTotal' => (string) Decimal::sum($asset->depreciations->pluck('amount')),
            'depreciation' => array_filter([
                __('Depreciation method') => $asset->method->label(),
                __('Useful life') => __(':months months', ['months' => $asset->useful_life_months])
                    .' ('.__(':years years', ['years' => self::quantity(round($years, 2))]).')',
                __('Yearly rate') => $asset->method === DepreciationMethod::DecliningBalance
                    ? ($asset->declining_rate !== null ? self::percent($asset->declining_rate) : __('2 ÷ years (double declining)'))
                    : null,
                __('Depreciation start') => self::date($asset->depreciation_start_date),
                __('Salvage value') => $money($asset->salvage_value),
                __('Depreciation already taken') => (float) $asset->opening_depreciation > 0 ? $money($asset->opening_depreciation) : null,
                __('Depreciated until') => self::date($asset->depreciated_until) ?? __('Not depreciated yet'),
                __('Paid from') => $asset->fundingAccount?->label() ?? __('Already in the books'),
                __('Disposal date') => self::date($asset->disposal_date),
                __('Sale price') => $asset->disposal_proceeds !== null ? $money($asset->disposal_proceeds) : null,
            ], fn (mixed $value): bool => filled($value)),
        ];
    }
}
