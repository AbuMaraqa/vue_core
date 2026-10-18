<?php

namespace App\Modules\Payroll\Models;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use Database\Factories\PayrollRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * The payroll of a period: one payslip per employee.
 *
 * @property int $id
 * @property string|null $number
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon|null $payment_date
 * @property int|null $bank_account_id
 * @property PayrollRunStatus $status
 * @property string $gross_total
 * @property string $deductions_total
 * @property string $employer_total
 * @property string $net_total
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BankAccount|null $bankAccount
 * @property-read User|null $creator
 * @property-read Collection<int, Payslip> $payslips
 */
#[Fillable([
    'number', 'period_start', 'period_end', 'payment_date', 'bank_account_id', 'status', 'gross_total',
    'deductions_total', 'employer_total', 'net_total', 'notes', 'created_by',
])]
#[UseFactory(PayrollRunFactory::class)]
class PayrollRun extends Model
{
    /** @use HasFactory<PayrollRunFactory> */
    use HasFactory;

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return MorphMany<JournalEntry, $this>
     */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function label(): string
    {
        return $this->number ?? $this->period_start->format('Y-m');
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'payment_date' => 'date',
            'status' => PayrollRunStatus::class,
            'gross_total' => 'decimal:4',
            'deductions_total' => 'decimal:4',
            'employer_total' => 'decimal:4',
            'net_total' => 'decimal:4',
        ];
    }
}
