<?php

namespace App\Modules\Assets\Actions;

use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use Illuminate\Support\Facades\DB;

/**
 * Cancel an asset registered by mistake, before any depreciation: its
 * acquisition entry, if any, is reversed.
 */
class CancelAsset
{
    public function __construct(protected Ledger $ledger) {}

    public function handle(Asset $asset): Asset
    {
        return DB::transaction(function () use ($asset): Asset {
            $asset = Asset::query()->lockForUpdate()->findOrFail($asset->id);

            if ($asset->status !== AssetStatus::Active || $asset->depreciations()->exists()) {
                throw new AccountingException(__('Only an asset with no depreciation can be cancelled. Dispose of it instead.'));
            }

            $this->ledger->reverseEntriesOf($asset, $this->ledger->openDateFor($asset->acquisition_date), __('Cancellation of :number', ['number' => $asset->number]));

            $asset->update(['status' => AssetStatus::Cancelled]);

            return $asset;
        });
    }
}
