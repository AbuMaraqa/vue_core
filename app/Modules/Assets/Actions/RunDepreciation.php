<?php

namespace App\Modules\Assets\Actions;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Enums\DocumentType;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DocumentNumbers;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\DepreciationRun;
use App\Modules\Assets\Support\DepreciationSchedule;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Post the depreciation of every asset in use up to a month-end. An asset
 * that missed earlier runs catches up in this one. The entry groups the
 * amounts by account and cost center:
 *
 *   Dr depreciation expense, Cr accumulated depreciation
 */
class RunDepreciation
{
    public function __construct(
        protected Ledger $ledger,
        protected DocumentNumbers $numbers,
        protected DepreciationSchedule $schedule,
    ) {}

    public function handle(CarbonInterface $month): DepreciationRun
    {
        $periodEnd = $month->copy()->endOfMonth();

        return DB::transaction(function () use ($periodEnd): DepreciationRun {
            $this->ledger->ensureDateIsOpen($periodEnd);

            $assets = Asset::query()
                ->with('category')
                ->where('status', AssetStatus::Active)
                ->whereDate('depreciation_start_date', '<=', $periodEnd->toDateString())
                ->where(fn ($query) => $query->whereNull('depreciated_until')->orWhereDate('depreciated_until', '<', $periodEnd->toDateString()))
                ->lockForUpdate()
                ->get();

            $due = $assets->mapWithKeys(fn (Asset $asset): array => [$asset->id => $this->schedule->due($asset, $periodEnd)])
                ->filter(fn (array $due): bool => Decimal::isPositive($due['amount']));

            if ($due->isEmpty()) {
                throw new AccountingException(__('There is no depreciation to post up to :date.', ['date' => $periodEnd->toDateString()]));
            }

            $userId = Auth::id();
            $run = DepreciationRun::query()->create([
                'number' => $this->numbers->next(DocumentType::DepreciationRun, $periodEnd),
                'period_end' => $periodEnd->toDateString(),
                'total' => Decimal::round(Decimal::sum($due->pluck('amount'))),
                'status' => DocumentStatus::Posted,
                'created_by' => is_int($userId) ? $userId : null,
            ]);

            /** @var array<string, array{debit: int, credit: int, cost_center: int|null, amount: BigDecimal}> $groups */
            $groups = [];

            foreach ($assets->whereIn('id', $due->keys()) as $asset) {
                $amount = $due[$asset->id];

                $asset->depreciations()->create([
                    'depreciation_run_id' => $run->id,
                    'from_date' => $amount['from'],
                    'to_date' => $amount['to'],
                    'months' => $amount['months'],
                    'amount' => $amount['amount'],
                    'previous_depreciated_until' => $asset->depreciated_until?->toDateString(),
                ]);

                $this->applyTo($asset, $amount['amount'], (string) $amount['to']);

                $key = "{$asset->category->depreciation_expense_account_id}-{$asset->category->accumulated_depreciation_account_id}-{$asset->cost_center_id}";
                $groups[$key] ??= [
                    'debit' => $asset->category->depreciation_expense_account_id,
                    'credit' => $asset->category->accumulated_depreciation_account_id,
                    'cost_center' => $asset->cost_center_id,
                    'amount' => BigDecimal::zero(),
                ];
                $groups[$key]['amount'] = $groups[$key]['amount']->plus($amount['amount']);
            }

            $entry = JournalEntryBuilder::make($periodEnd, __('Depreciation up to :date', ['date' => $periodEnd->toDateString()]))
                ->reference($run->number)
                ->source($run);

            foreach ($groups as $group) {
                $entry->debit($group['debit'], $group['amount'], costCenterId: $group['cost_center'])
                    ->credit($group['credit'], $group['amount']);
            }

            $entry->post();

            return $run;
        });
    }

    /**
     * Add depreciation to the asset; it is fully depreciated once only its
     * salvage value is left.
     */
    public function applyTo(Asset $asset, string $amount, string $until): void
    {
        $asset->accumulated_depreciation = Decimal::round(Decimal::of($asset->accumulated_depreciation)->plus($amount));
        $asset->depreciated_until = Carbon::parse($until);

        if (! Decimal::isPositive($asset->depreciableRemaining())) {
            $asset->status = AssetStatus::FullyDepreciated;
        }

        $asset->save();
    }
}
