<?php

namespace App\Modules\Core\Actions\Media;

use App\Modules\Core\Data\MediaInputData;
use App\Modules\Core\Models\TemporaryUpload;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Apply the changes of a media field to a model collection: remove the media
 * the user deleted, copy the files picked from the media library, and attach
 * the new uploads.
 */
class SyncMediaCollection
{
    public function handle(HasMedia $model, string $collection, MediaInputData $input): void
    {
        $model->getMedia($collection)
            ->whereIn('id', $input->removed)
            ->each(fn (Media $media) => $media->delete());

        Media::query()
            ->whereKey($input->library)
            ->get()
            ->each(fn (Media $media) => $media->copy($model, $collection));

        TemporaryUpload::query()
            ->whereKey($input->uploads)
            ->where('user_id', Auth::id())
            ->get()
            ->each(function (TemporaryUpload $upload) use ($model, $collection): void {
                $model->addMedia($upload->absolutePath())
                    ->usingName(pathinfo($upload->original_name, PATHINFO_FILENAME))
                    ->usingFileName($upload->original_name)
                    ->toMediaCollection($collection);

                $upload->delete();
            });
    }
}
