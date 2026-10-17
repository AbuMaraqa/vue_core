<?php

namespace App\Modules\Foundation\Notifications;

use App\Modules\Foundation\Enums\NotificationLevel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * The base of every notification shown in the notification bell.
 *
 * The title and body are stored as English translation keys with their
 * parameters, and translated when they are displayed, so each user reads them
 * in their own language. The link is stored as a route name for the same
 * reason: it is generated with the reader's language segment.
 *
 * ```php
 * class OrderShipped extends SystemNotification
 * {
 *     public function __construct(protected Order $order) {}
 *
 *     protected function title(): string { return 'Order shipped'; }
 *     protected function body(): string { return 'Order #:number is on its way.'; }
 *     protected function parameters(): array { return ['number' => $this->order->number]; }
 *     protected function route(): ?array { return ['admin.orders.edit', ['order' => $this->order->id]]; }
 * }
 * ```
 */
abstract class SystemNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The title, as an English translation key.
     */
    abstract protected function title(): string;

    /**
     * The message, as an English translation key with `:placeholders`.
     */
    abstract protected function body(): string;

    /**
     * The values of the placeholders of the title and body.
     *
     * @return array<string, string|int|float>
     */
    protected function parameters(): array
    {
        return [];
    }

    /**
     * The page the notification opens, as a route name and its parameters.
     *
     * @return array{0: string, 1?: array<string, mixed>}|null
     */
    protected function route(): ?array
    {
        return null;
    }

    protected function level(): NotificationLevel
    {
        return NotificationLevel::Info;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{title: string, body: string, parameters: array<string, string|int|float>, route: array{0: string, 1?: array<string, mixed>}|null, level: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'parameters' => $this->parameters(),
            'route' => $this->route(),
            'level' => $this->level()->value,
        ];
    }
}
