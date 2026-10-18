<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Accounting\Models\CostCenter;
use Database\Factories\PayslipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One employee's pay for a run: basic salary (for the days worked) plus
 * earnings, minus deductions, gives the net pay. Employer contributions are
 * recorded but not paid to the employee.
 *
 * @property int $id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property int|null $cost_center_id
 * @property int $days_worked
 * @property int $days_in_period
 * @property string $basic
 * @property string $earnings_total
 * @property string $deductions_total
 * @property string $employer_total
 * @property string $net_pay
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PayrollRun $run
 * @property-read Employee $employee
 * @property-read CostCenter|null $costCenter
 * @property-read Collection<int, PayslipLine> $lines
 */
#[Fillable([
    'payroll_run_id', 'employee_id', 'cost_center_id', 'days_worked', 'days_in_period', 'basic',
    'earnings_total', 'deductions_total', 'employer_total', 'net_pay',
])]
#[UseFactory(PayslipFactory::class)]
class Payslip extends Model
{
    /** @use HasFactory<PayslipFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * @return HasMany<PayslipLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class);
    }

    protected function casts(): array
    {
        return [
            'days_worked' => 'integer',
            'days_in_period' => 'integer',
            'basic' => 'decimal:4',
            'earnings_total' => 'decimal:4',
            'deductions_total' => 'decimal:4',
            'employer_total' => 'decimal:4',
            'net_pay' => 'decimal:4',
        ];
    }
}
