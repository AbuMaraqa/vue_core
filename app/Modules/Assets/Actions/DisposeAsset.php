<?php

namespace App\Modules\Assets\Actions;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Support\DepreciationSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sell or scrap an asset. Depreciation is first brought up to the month of
 * disposal, then the asset leaves the books:
 *
 *   Dr accumulated depreciation   all of it
 *   Dr proceeds account           the sale price (bank, or the buyer's receivable)
 *   Cr asset account              cost
 *   and the difference as a gain (Cr) or loss (Dr) on disposal.
 */
class DisposeAsset
{
    public function __construct(
        protected Ledger $ledger,
        protected DefaultAccounts $defaults,
        protected DepreciationSchedule $schedule,
        protected RunDepreciation $runDepreciation,
    ) {}

    public function handle(Asset $asset, string $date, string $proceeds = '0', ?int $proceedsAccountId = null): Asset
    {
        return DB::transaction(function () use ($asset, $date, $proceeds, $proceedsAccountId): Asset {
            $asset = Asset::query()->with('category')->lockForUpdate()->findOrFail($asset->id);

            if (! $asset->status->isOwned()) {
                throw new AccountingException(__('The asset :asset is not in use.', ['asset' => $asset->number]));
            }

            if (Decimal::isPositive($proceeds) && $proceedsAccountId === null) {
                throw new AccountingException(__('Choose the account the sale price goes to.'));
            }

            $this->ledger->ensureDateIsOpen($date);

            $catchUp = $asset->status === AssetStatus::Active
                ? $this->schedule->due($asset, Carbon::parse($date)->endOfMonth())
                : ['amount' => '0', 'months' => 0, 'from' => null, 'to' => null];

            $entry = JournalEntryBuilder::make($date, __('Disposal of :asset', ['asset' => "{$asset->number} {$asset->name}"]))
                ->reference($asset->number)
                ->source($asset);

            if (Decimal::isPositive($catchUp['amount'])) {
                $asset->depreciations()->create([
                    'from_date' => $catchUp['from'],
                    'to_date' => $catchUp['to'],
                    'months' => $catchUp['months'],
                    'amount' => $catchUp['amount'],
                    'previous_depreciated_until' => $asset->depreciated_until?->toDateString(),
                ]);

                $this->runDepreciation->applyTo($asset, $catchUp['amount'], (string) $catchUp['to']);

                $entry->debit($asset->category->depreciation_expense_account_id, $catchUp['amount'], costCenterId: $asset->cost_center_id)
                    ->credit($asset->category->accumulated_depreciation_account_id, $catchUp['amount']);
            }

            $result = Decimal::of($proceeds)->minus($asset->bookValue());

            $entry->debit($asset->category->accumulated_depreciation_account_id, $asset->accumulated_depreciation)
                ->debit((int) $proceedsAccountId, $proceeds)
                ->credit($asset->category->asset_account_id, $asset->cost, costCenterId: $asset->cost_center_id);

            if ($result->isPositive()) {
                $entry->credit($this->defaults->id(AccountRole::AssetDisposalGain), $result);
            } elseif ($result->isNegative()) {
                $entry->debit($this->defaults->id(AccountRole::AssetDisposalLoss), $result->abs());
            }

            $entry->post();

            $asset->update([
                'status' => AssetStatus::Disposed,
                'disposal_date' => $date,
                'disposal_proceeds' => Decimal::round($proceeds),
                'disposal_account_id' => $proceedsAccountId,
            ]);

            return $asset;
        });
    }
}
