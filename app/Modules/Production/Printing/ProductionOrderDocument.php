<?php

namespace App\Modules\Production\Printing;

use App\Modules\Accounting\Printing\FinancialDocument;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Production\Enums\ProductionOrderStatus;
use App\Modules\Production\Models\ProductionOrder;

/**
 * The printed production order: the product and its quantity, the materials
 * issued (and used, once completed) with their cost, the other costs, and
 * the real cost of the product when the order is completed.
 */
class ProductionOrderDocument extends FinancialDocument
{
    public function __construct(protected ProductionOrder $order)
    {
        $order->loadMissing([
            'billOfMaterials', 'item.unit', 'materialsWarehouse', 'productWarehouse', 'contact', 'costCenter',
            'materials.item.unit', 'costs.account',
        ]);
    }

    public function title(): string
    {
        return __('Production order');
    }

    public function number(): ?string
    {
        return $this->order->number;
    }

    /**
     * Only a completed order is final: drafts and orders in progress can
     * still change, and cancelled ones were never produced.
     */
    public function status(): ?array
    {
        $status = $this->order->status;

        return match ($status) {
            ProductionOrderStatus::Draft => ['label' => $status->label(), 'tone' => 'warning'],
            ProductionOrderStatus::InProgress => ['label' => $status->label(), 'tone' => 'neutral'],
            ProductionOrderStatus::Cancelled => ['label' => $status->label(), 'tone' => 'danger'],
            ProductionOrderStatus::Completed => null,
        };
    }

    public function details(): array
    {
        return [
            __('Date') => self::date($this->order->date),
            __('Reference') => $this->order->reference,
            __('Started on') => self::date($this->order->started_on),
            __('Completed on') => self::date($this->order->completed_on),
            __('Materials warehouse') => $this->order->materialsWarehouse->translate('name'),
            __('Finished goods warehouse') => $this->order->productWarehouse->translate('name'),
            __('Bill of materials') => $this->order->billOfMaterials?->label(),
            __('Cost center') => $this->order->costCenter?->label(),
        ];
    }

    public function signatures(): array
    {
        return [__('Prepared by'), __('Production supervisor'), __('Store keeper')];
    }

    protected function view(): string
    {
        return 'production::print.production-order';
    }

    protected function data(): array
    {
        return [
            'order' => $this->order,
            'isStarted' => in_array($this->order->status, [ProductionOrderStatus::InProgress, ProductionOrderStatus::Completed], true),
            'isCompleted' => $this->order->status === ProductionOrderStatus::Completed,
            'currency' => app(Currencies::class)->base(),
        ];
    }
}
