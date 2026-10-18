<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Payroll\Enums\PayComponentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One component on a payslip, with its amount for the month.
 *
 * @property int $id
 * @property int $payslip_id
 * @property int $pay_component_id
 * @property PayComponentType $type
 * @property string $amount
 * @property-read Payslip $payslip
 * @property-read PayComponent $component
 */
#[Fillable(['payslip_id', 'pay_component_id', 'type', 'amount'])]
class PayslipLine extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Payslip, $this>
     */
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
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
            'type' => PayComponentType::class,
            'amount' => 'decimal:4',
        ];
    }
}
