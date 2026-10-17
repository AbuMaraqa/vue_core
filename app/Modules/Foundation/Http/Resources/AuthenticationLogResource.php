<?php

namespace App\Modules\Foundation\Http\Resources;

use App\Modules\Foundation\Models\AuthenticationLog;
use App\Modules\Foundation\Support\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuthenticationLog
 */
class AuthenticationLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event->value,
            'event_label' => $this->event->label(),
            'event_color' => $this->event->color(),
            'email' => $this->email,
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'ip_address' => $this->ip_address,
            'device' => UserAgent::parse($this->user_agent)->toArray(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
