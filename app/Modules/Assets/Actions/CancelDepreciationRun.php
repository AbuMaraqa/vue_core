<?php

namespace App\Modules\Assets\Actions;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\DepreciationRun;
use Illuminate\Support\Facades\DB;

/**
 * Undo the latest depreciation run: its entry is reversed and every asset
 * returns to where it was before it.
 */
class CancelDepreciationRun
{
    public function __construct(protected Ledger $ledger) {}

    public function handle(DepreciationRun $run): DepreciationRun
    {
        return DB::transaction(function () use ($run): DepreciationRun {
            $latest = DepreciationRun::query()->where('status', DocumentStatus::Posted)->latest('period_end')->latest('id')->lockForUpdate()->first();

            if ($latest?->id !== $run->id) {
                throw new AccountingException(__('Only the latest depreciation run can be cancelled.'));
            }

            $this->ledger->reverseEntriesOf($run, $this->ledger->openDateFor($run->period_end), __('Cancellation of :number', ['number' => $run->number]));

            foreach ($run->depreciations()->with('asset')->get() as $depreciation) {
                $asset = $depreciation->asset;

                if ($asset->status === AssetStatus::Disposed) {
                    throw new AccountingException(__('The asset :asset was disposed after this run.', ['asset' => $asset->number]));
                }

                $asset->update([
                    'accumulated_depreciation' => Decimal::round(Decimal::of($asset->accumulated_depreciation)->minus($depreciation->amount)),
                    'depreciated_until' => $depreciation->previous_depreciated_until,
                    'status' => AssetStatus::Active,
                ]);
            }

            $run->depreciations()->delete();
            $run->update(['status' => DocumentStatus::Cancelled]);

            return $run;
        });
    }
}
