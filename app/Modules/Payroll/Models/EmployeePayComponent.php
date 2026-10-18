<?php

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A component an employee gets, with its value for them: an amount, or a
 * percent of basic salary, by the component's calculation.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $pay_component_id
 * @property string $value
 * @property-read Employee $employee
 * @property-read PayComponent $component
 */
#[Fillable(['employee_id', 'pay_component_id', 'value'])]
class EmployeePayComponent extends Model
{
    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<PayComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class, 'pay_component_id');
    }

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
        ];
    }
}
