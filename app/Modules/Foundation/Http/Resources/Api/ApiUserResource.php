<?php

namespace App\Modules\Foundation\Http\Resources\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;
use Spatie\Permission\Models\Role;

/**
 * The signed-in user as seen by API clients, with what they may do.
 *
 * @mixin User
 */
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Sara'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'locale', type: 'string', example: 'ar', nullable: true),
        new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string')),
    ],
)]
class ApiUserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'locale' => $this->locale,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'roles' => $this->roles->map(fn (Role $role): string => $role->name)->values()->all(),
            'permissions' => $this->permissionNames(),
        ];
    }
}
