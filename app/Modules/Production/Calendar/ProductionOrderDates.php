<?php

namespace App\Modules\Production\Calendar;

use App\Models\User;
use App\Modules\Foundation\Support\Calendar\CalendarEntry;
use App\Modules\Foundation\Support\Calendar\CalendarSource;
use App\Modules\Production\Enums\ProductionOrderStatus;
use App\Modules\Production\Models\ProductionOrder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Production orders on the day that matters for their stage: planned
 * (draft), started (in progress) or completed. A draft whose day has passed
 * is overdue: the workshop has not started it.
 */
class ProductionOrderDates extends CalendarSource
{
    public function key(): string
    {
        return 'production';
    }

    public function label(): string
    {
        return __('Production orders');
    }

    public function color(): string
    {
        return 'brand';
    }

    public function permission(): string
    {
        return 'Production Order View List';
    }

    public function entries(CarbonInterface $from, CarbonInterface $to, User $user): iterable
    {
        $range = [$from->toDateString(), $to->toDateString()];
        $today = now()->startOfDay();

        return ProductionOrder::query()
            ->with(['item', 'contact'])
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $draft) => $draft->where('status', ProductionOrderStatus::Draft)->whereBetween('date', $range))
                ->orWhere(fn (Builder $started) => $started->where('status', ProductionOrderStatus::InProgress)->whereBetween('started_on', $range))
                ->orWhere(fn (Builder $completed) => $completed->where('status', ProductionOrderStatus::Completed)->whereBetween('completed_on', $range)))
            ->get()
            ->map(fn (ProductionOrder $order): CalendarEntry => $this->entry($order, $order->status === ProductionOrderStatus::Draft && $order->date->lessThan($today)));
    }

    public function overdue(CarbonInterface $today, User $user): iterable
    {
        return ProductionOrder::query()
            ->with(['item', 'contact'])
            ->where('status', ProductionOrderStatus::Draft)
            ->whereDate('date', '<', $today->toDateString())
            ->orderBy('date')
            ->limit(100)
            ->get()
            ->map(fn (ProductionOrder $order): CalendarEntry => $this->entry($order, true));
    }

    protected function entry(ProductionOrder $order, bool $overdue): CalendarEntry
    {
        return new CalendarEntry(
            id: "production-{$order->id}",
            type: $this->key(),
            date: match ($order->status) {
                ProductionOrderStatus::InProgress => $order->started_on ?? $order->date,
                ProductionOrderStatus::Completed => $order->completed_on ?? $order->date,
                default => $order->date,
            },
            title: "{$order->number} · {$order->item->translate('name')}",
            subtitle: $order->contact->name ?? $order->description,
            url: route('admin.production.orders.show', $order),
            status: $order->status->label(),
            color: match (true) {
                $overdue => 'error',
                $order->status === ProductionOrderStatus::Completed => 'success',
                $order->status === ProductionOrderStatus::InProgress => 'warning',
                default => null,
            },
            overdue: $overdue,
        );
    }
}
