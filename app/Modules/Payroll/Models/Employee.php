<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Accounting\Models\CostCenter;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Someone on the payroll. Their salary cost goes to their cost center
 * (department); journal lines of their pay name them as partner.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $national_id
 * @property string|null $position
 * @property int|null $cost_center_id
 * @property Carbon $hire_date
 * @property Carbon|null $termination_date
 * @property string $basic_salary
 * @property string $payment_method
 * @property string|null $bank_name
 * @property string|null $iban
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CostCenter|null $costCenter
 * @property-read Collection<int, EmployeePayComponent> $components
 */
#[Fillable([
    'code', 'name', 'national_id', 'position', 'cost_center_id', 'hire_date', 'termination_date', 'basic_salary',
    'payment_method', 'bank_name', 'iban', 'email', 'phone', 'notes', 'is_active',
])]
#[UseFactory(EmployeeFactory::class)]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * The components the employee receives or has deducted, with their value.
     *
     * @return HasMany<EmployeePayComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(EmployeePayComponent::class);
    }

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * Employees on the payroll for some of the period.
     *
     * @param  Builder<Employee>  $query
     */
    public function scopeEmployedDuring(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $query->where('is_active', true)
            ->whereDate('hire_date', '<=', $end->toDateString())
            ->where(fn (Builder $current) => $current->whereNull('termination_date')->orWhereDate('termination_date', '>=', $start->toDateString()));
    }

    public function label(): string
    {
        return "{$this->code} - {$this->name}";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('employees')
            ->logOnly(['code', 'name', 'position', 'basic_salary', 'cost_center_id', 'termination_date', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'termination_date' => 'date',
            'basic_salary' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }
}
