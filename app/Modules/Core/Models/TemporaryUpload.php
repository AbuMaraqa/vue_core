<?php

namespace App\Modules\Core\Models;

use App\Models\User;
use Database\Factories\TemporaryUploadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A file uploaded ahead of a form submission. It is attached to a model with
 * the media library when the form is saved, and pruned when it never is.
 *
 * @property string $id
 * @property int $user_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
#[UseFactory(TemporaryUploadFactory::class)]
class TemporaryUpload extends Model
{
    /** @use HasFactory<TemporaryUploadFactory> */
    use HasFactory, HasUuids, Prunable;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the absolute path of the stored file.
     */
    public function absolutePath(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    /**
     * Uploads that were never attached are removed after a day.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDay());
    }

    protected static function booted(): void
    {
        static::deleted(function (TemporaryUpload $upload): void {
            $directory = dirname($upload->path);

            // Only ever the upload's own folder: a path without one would
            // otherwise delete the whole disk, backups included.
            if (str_starts_with($directory, 'tmp/uploads/')) {
                Storage::disk($upload->disk)->deleteDirectory($directory);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
