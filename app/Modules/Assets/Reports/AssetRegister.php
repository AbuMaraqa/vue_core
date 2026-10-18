<?php

namespace App\Modules\Assets\Reports;

use App\Modules\Accounting\Support\Decimal;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Foundation\Exports\Report;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;

/**
 * The assets owned on a date with their cost, the depreciation taken up to
 * that date and their book value: the detail of the fixed assets on the
 * balance sheet.
 */
class AssetRegister
{
    /**
     * @return array{rows: list<array<string, mixed>>, totals: array{cost: string, accumulated: string, book_value: string}}
     */
    public function generate(CarbonInterface $asOf, ?int $categoryId = null): array
    {
        $date = $asOf->toDateString();
        $nextDay = $asOf->copy()->addDay()->toDateString();

        $assets = Asset::query()
            ->with('category')
            ->withSum(['depreciations as posted_depreciation' => fn ($query) => $query->where('to_date', '<', $nextDay)], 'amount')
            ->whereIn('status', [AssetStatus::Active, AssetStatus::FullyDepreciated, AssetStatus::Disposed])
            ->whereDate('acquisition_date', '<=', $date)
            ->where(fn ($query) => $query->whereNull('disposal_date')->orWhereDate('disposal_date', '>', $date))
            ->when($categoryId !== null, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('number')
            ->get();

        $totals = ['cost' => BigDecimal::zero(), 'accumulated' => BigDecimal::zero()];
        $rows = [];

        foreach ($assets as $asset) {
            $accumulated = Decimal::of($asset->opening_depreciation)->plus(Decimal::round((string) ($asset->getAttribute('posted_depreciation') ?? '0')));
            $totals['cost'] = $totals['cost']->plus($asset->cost);
            $totals['accumulated'] = $totals['accumulated']->plus($accumulated);

            $rows[] = [
                'id' => $asset->id,
                'number' => $asset->number,
                'name' => $asset->name,
                'category' => $asset->category->translate('name'),
                'acquisition_date' => $asset->acquisition_date->toDateString(),
                'cost' => Decimal::round($asset->cost),
                'accumulated' => Decimal::round($accumulated),
                'book_value' => Decimal::round(Decimal::of($asset->cost)->minus($accumulated)),
            ];
        }

        return [
            'rows' => $rows,
            'totals' => [
                'cost' => Decimal::round($totals['cost']),
                'accumulated' => Decimal::round($totals['accumulated']),
                'book_value' => Decimal::round($totals['cost']->minus($totals['accumulated'])),
            ],
        ];
    }

    public function export(CarbonInterface $asOf, ?int $categoryId = null): Report
    {
        $report = $this->generate($asOf, $categoryId);

        $rows = array_map(fn (array $row): array => [
            $row['number'], $row['name'], $row['category'], $row['acquisition_date'], $row['cost'], $row['accumulated'], $row['book_value'],
        ], $report['rows']);

        $rows[] = ['', __('Total'), '', '', $report['totals']['cost'], $report['totals']['accumulated'], $report['totals']['book_value']];

        return new Report(
            'Fixed Asset Register',
            ['Number', 'Asset', 'Category', 'Acquired', 'Cost', 'Accumulated depreciation', 'Book value'],
            $rows,
            [__('As of :date', ['date' => $asOf->toDateString()])],
        );
    }
}
