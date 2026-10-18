<?php

namespace App\Modules\Assets\Actions;

use App\Modules\Accounting\Enums\DocumentType;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DocumentNumbers;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Register an asset. With a funding account (the bank it was paid from, a
 * vendor's payable, or opening balance equity for an asset carried over),
 * its acquisition is posted:
 *
 *   Dr asset account               cost
 *   Cr accumulated depreciation    opening depreciation (carried assets)
 *   Cr funding account             the rest
 *
 * Without one, the cost is already in the books (e.g. through a purchase
 * bill on the asset account) and only the register is updated.
 */
class CreateAsset
{
    public function __construct(
        protected Ledger $ledger,
        protected DocumentNumbers $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Asset
    {
        return DB::transaction(function () use ($data): Asset {
            $category = AssetCategory::query()->whereKey($data['category_id'])->firstOrFail();
            $opening = Decimal::round($data['opening_depreciation'] ?? '0');
            $userId = Auth::id();

            if (filled($data['funding_account_id'] ?? null)) {
                $this->ledger->ensureDateIsOpen($data['acquisition_date']);
            }

            $asset = Asset::query()->create([
                ...$data,
                'number' => $this->numbers->next(DocumentType::Asset, $data['acquisition_date']),
                'method' => $data['method'] ?? $category->method,
                'useful_life_months' => $data['useful_life_months'] ?? $category->useful_life_months,
                'salvage_value' => $data['salvage_value'] ?? '0',
                'opening_depreciation' => $opening,
                'accumulated_depreciation' => $opening,
                'depreciation_start_date' => $data['depreciation_start_date'] ?? Carbon::parse($data['acquisition_date'])->toDateString(),
                'status' => AssetStatus::Active,
                'created_by' => is_int($userId) ? $userId : null,
            ]);

            if ($asset->funding_account_id !== null) {
                JournalEntryBuilder::make($asset->acquisition_date, __('Acquisition of :asset', ['asset' => "{$asset->number} {$asset->name}"]))
                    ->reference($asset->number)
                    ->source($asset)
                    ->debit($category->asset_account_id, $asset->cost, costCenterId: $asset->cost_center_id)
                    ->credit($category->accumulated_depreciation_account_id, $opening)
                    ->credit($asset->funding_account_id, Decimal::of($asset->cost)->minus($opening))
                    ->post();
            }

            return $asset;
        });
    }
}
