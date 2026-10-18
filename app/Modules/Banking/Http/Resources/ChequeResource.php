<?php

namespace App\Modules\Banking\Http\Resources;

use App\Modules\Banking\Enums\ChequeAction;
use App\Modules\Banking\Enums\ChequeStatus;
use App\Modules\Banking\Models\Cheque;
use App\Modules\Banking\Models\ChequeEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cheque
 */
class ChequeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canManage = $request->user()?->can('manage', $this->resource) ?? false;

        return [
            'id' => $this->id,
            'direction' => $this->direction->value,
            'direction_label' => $this->direction->label(),
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn (): ?string => $this->contact?->name),
            'bank_account' => $this->whenLoaded('bankAccount', fn (): ?string => $this->bankAccount?->label()),
            'drawee_bank' => $this->drawee_bank,
            'amount' => (string) $this->amount,
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency', fn (): array => ['code' => $this->currency->code, 'decimal_places' => $this->currency->decimal_places]),
            'exchange_rate' => (string) $this->exchange_rate,
            'issue_date' => $this->issue_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'is_overdue' => $this->status->isOpen() && $this->due_date->isPast() && ! $this->due_date->isToday(),
            'counter_account' => $this->whenLoaded('counterAccount', fn (): string => $this->counterAccount->label()),
            'endorsed_to' => $this->whenLoaded('endorsedTo', fn (): ?string => $this->endorsedTo?->name),
            'description' => $this->description,
            'events' => $this->whenLoaded('events', fn (): array => $this->events->map(fn (ChequeEvent $event): array => [
                'id' => $event->id,
                'action' => $event->action->label(),
                'from_status' => $event->from_status?->label(),
                'to_status' => $event->to_status->label(),
                'date' => $event->date->toDateString(),
                'journal_entry' => $event->journalEntry ? ['id' => $event->journalEntry->id, 'number' => $event->journalEntry->number] : null,
                'note' => $event->note,
                'is_undone' => $event->is_undone,
                'created_by' => $event->creator?->name,
            ])->values()->all()),
            'actions' => $canManage
                ? array_map(fn (ChequeAction $action): array => [
                    'value' => $action->value,
                    'label' => $action->label(),
                    'needs_bank_account' => $action === ChequeAction::Deposit || ($action === ChequeAction::Collect && $this->status === ChequeStatus::InHand),
                    'needs_contact' => $action === ChequeAction::Endorse,
                ], $this->resource->availableActions())
                : [],
            'can' => [
                'manage' => $canManage,
            ],
        ];
    }
}
