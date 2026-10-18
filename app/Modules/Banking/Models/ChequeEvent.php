<?php

namespace App\Modules\Banking\Models;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Banking\Enums\ChequeAction;
use App\Modules\Banking\Enums\ChequeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One step of a cheque's history, with the journal entry it posted.
 *
 * @property int $id
 * @property int $cheque_id
 * @property ChequeAction $action
 * @property ChequeStatus|null $from_status
 * @property ChequeStatus $to_status
 * @property Carbon $date
 * @property int|null $journal_entry_id
 * @property string|null $note
 * @property bool $is_undone
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Cheque $cheque
 * @property-read JournalEntry|null $journalEntry
 * @property-read User|null $creator
 */
#[Fillable(['cheque_id', 'action', 'from_status', 'to_status', 'date', 'journal_entry_id', 'note', 'is_undone', 'created_by'])]
class ChequeEvent extends Model
{
    /**
     * @return BelongsTo<Cheque, $this>
     */
    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'action' => ChequeAction::class,
            'from_status' => ChequeStatus::class,
            'to_status' => ChequeStatus::class,
            'date' => 'date',
            'is_undone' => 'boolean',
        ];
    }
}
