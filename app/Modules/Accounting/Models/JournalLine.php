<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One debit or credit of a journal entry. `partner` is the customer, vendor
 * or employee the line concerns; it builds their statements.
 *
 * @property int $id
 * @property int $journal_entry_id
 * @property int $account_id
 * @property int|null $cost_center_id
 * @property string|null $partner_type
 * @property int|null $partner_id
 * @property string|null $description
 * @property string $debit
 * @property string $credit
 * @property string $base_debit
 * @property string $base_credit
 * @property int $sort
 * @property int|null $reconciliation_id
 * @property-read JournalEntry $entry
 * @property-read Account $account
 * @property-read CostCenter|null $costCenter
 * @property-read Model|null $partner
 */
#[Fillable([
    'journal_entry_id', 'account_id', 'cost_center_id', 'partner_type', 'partner_id', 'description',
    'debit', 'credit', 'base_debit', 'base_credit', 'sort', 'reconciliation_id',
])]
class JournalLine extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function partner(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:4',
            'credit' => 'decimal:4',
            'base_debit' => 'decimal:4',
            'base_credit' => 'decimal:4',
            'sort' => 'integer',
        ];
    }
}
