<?php

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin Media
 */
class MediaResource extends JsonResource
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
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'is_image' => Str::startsWith((string) $this->mime_type, 'image/'),
            'size' => $this->size,
            'human_size' => $this->human_readable_size,
            'url' => $this->getFullUrl(),
            'collection' => $this->collection_name,
            'owner' => [
                'type' => class_basename($this->model_type),
                'id' => $this->model_id,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'can' => [
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
