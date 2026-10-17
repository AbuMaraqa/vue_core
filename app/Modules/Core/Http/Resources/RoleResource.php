<?php

namespace App\Modules\Core\Http\Resources;

use App\Modules\Core\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
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
            'is_protected' => UserRole::isProtected($this->name),
            'is_super_admin' => $this->name === UserRole::SuperAdmin->value,
            'permissions' => $this->whenLoaded('permissions', fn (): array => $this->permissions->map(fn (Permission $permission): string => $permission->name)->all()),
            'permissions_count' => $this->whenCounted('permissions'),
            'users_count' => $this->whenCounted('users'),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
