<?php

namespace App\Modules\Accounting\Support;

use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Enums\TaxScope;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\Tax;

/**
 * The option lists the forms of every financial module need: accounts, cost
 * centers, currencies and taxes, already translated.
 */
class AccountingOptions
{
    public function __construct(
        protected AccountTree $tree,
        protected Currencies $currencies,
    ) {}

    /**
     * Postable accounts, optionally only those of the given subtypes.
     *
     * @return list<array{value: int, label: string, hint: string}>
     */
    public function accounts(AccountSubtype ...$subtypes): array
    {
        return $this->tree->postableOptions(...$subtypes);
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public function costCenters(): array
    {
        return array_values(CostCenter::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (CostCenter $center): array => ['value' => $center->id, 'label' => $center->label()])
            ->all());
    }

    /**
     * @return list<array{value: int, label: string, code: string, decimal_places: int, is_base: bool}>
     */
    public function currencies(): array
    {
        $baseId = $this->currencies->base()->id;

        return array_values(Currency::query()
            ->where('is_active', true)
            ->orderByRaw('id = ? desc', [$baseId])
            ->orderBy('code')
            ->get()
            ->map(fn (Currency $currency): array => [
                'value' => $currency->id,
                'label' => "{$currency->code} - {$currency->translate('name')}",
                'code' => $currency->code,
                'decimal_places' => $currency->decimal_places,
                'is_base' => $currency->id === $baseId,
            ])
            ->all());
    }

    /**
     * @return list<array{value: int, label: string, rate: string}>
     */
    public function taxes(?TaxScope $scope = null): array
    {
        return array_values(Tax::query()
            ->where('is_active', true)
            ->when($scope === TaxScope::Sales, fn ($query) => $query->whereIn('scope', [TaxScope::Sales, TaxScope::Both]))
            ->when($scope === TaxScope::Purchases, fn ($query) => $query->whereIn('scope', [TaxScope::Purchases, TaxScope::Both]))
            ->orderBy('rate')
            ->get()
            ->map(fn (Tax $tax): array => [
                'value' => $tax->id,
                'label' => $tax->translate('name'),
                'rate' => (string) $tax->rate,
            ])
            ->all());
    }
}
