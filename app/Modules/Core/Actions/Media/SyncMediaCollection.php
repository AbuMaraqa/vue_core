<?php

namespace App\Modules\Core\Actions\Media;

use App\Modules\Core\Data\MediaInputData;
use App\Modules\Core\Models\Site;
use App\Modules\Core\Models\TemporaryUpload;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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

        // Only files of the shared media library can be picked: the id of any
        // other media (an employee photo, a scanned cheque) is ignored.
        Media::query()
            ->whereKey($input->library)
            ->where('model_type', (new Site)->getMorphClass())
            ->where('collection_name', Site::LIBRARY)
            ->get()
            ->each(fn (Media $media) => $media->copy($model, $collection));

        TemporaryUpload::query()
            ->whereKey($input->uploads)
            ->where('user_id', Auth::id())
            ->get()
            ->each(function (TemporaryUpload $upload) use ($model, $collection): void {
                $model->addMedia($upload->absolutePath())
                    ->usingName(pathinfo($upload->original_name, PATHINFO_FILENAME))
                    ->usingFileName($this->safeFileName($upload))
                    ->toMediaCollection($collection);

                $upload->delete();
            });
    }

    /**
     * The stored name keeps the user's name but takes its extension from the
     * detected content (the temporary file was saved with it), so a file
     * named `x.html` that sniffs as a PNG is served as `x.png`, never as a
     * page that could run scripts on this site.
     */
    protected function safeFileName(TemporaryUpload $upload): string
    {
        $name = Str::slug(pathinfo($upload->original_name, PATHINFO_FILENAME)) ?: 'file';
        $extension = Str::lower(pathinfo($upload->path, PATHINFO_EXTENSION));

        return $extension !== '' ? "{$name}.{$extension}" : $name;
    }
}
