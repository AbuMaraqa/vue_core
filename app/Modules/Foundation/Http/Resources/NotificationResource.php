<?php

namespace App\Modules\Foundation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;

/**
 * A notification written by SystemNotification, translated and linked for
 * the reader.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{title?: string, body?: string, parameters?: array<string, string|int|float>, route?: array{0: string, 1?: array<string, mixed>}|null, level?: string} $data */
        $data = $this->data;
        $parameters = $data['parameters'] ?? [];

        return [
            'id' => $this->id,
            'title' => __($data['title'] ?? '', $parameters),
            'body' => __($data['body'] ?? '', $parameters),
            'level' => $data['level'] ?? 'info',
            'url' => $this->url($data['route'] ?? null),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Build the link in the reader's language. A route that no longer exists,
     * e.g. after a module was removed, gives no link instead of an error.
     *
     * @param  array{0: string, 1?: array<string, mixed>}|null  $route
     */
    protected function url(?array $route): ?string
    {
        if ($route === null || ! Route::has($route[0])) {
            return null;
        }

        return route($route[0], $route[1] ?? [], absolute: false);
    }
}
