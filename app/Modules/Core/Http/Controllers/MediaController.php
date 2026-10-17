<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Actions\Media\SyncMediaCollection;
use App\Modules\Core\Data\MediaInputData;
use App\Modules\Core\Http\Requests\Media\StoreMediaRequest;
use App\Modules\Core\Http\Resources\MediaResource;
use App\Modules\Core\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The media library: every file attached to any model in the system.
 */
class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Media::class);

        return Inertia::render('admin/media/Index', [
            'media' => MediaResource::collection($this->query()->paginate(24)->withQueryString()),
            'collections' => Media::query()->distinct()->orderBy('collection_name')->pluck('collection_name'),
            'filters' => [
                'search' => $request->input('filter.search'),
                'type' => $request->input('filter.type'),
                'collection' => $request->input('filter.collection'),
            ],
            'canUpload' => Gate::allows('create', Media::class),
        ]);
    }

    /**
     * List images for the "choose from library" picker of the MediaUpload component.
     */
    public function browse(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Media::class);

        return MediaResource::collection(
            $this->query()->where('mime_type', 'like', 'image/%')->paginate(24)->withQueryString(),
        );
    }

    public function store(StoreMediaRequest $request, SyncMediaCollection $syncMedia): RedirectResponse
    {
        $syncMedia->handle(Site::current(), Site::LIBRARY, MediaInputData::from([
            'uploads' => $request->validated('uploads'),
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Files uploaded.')]);

        return to_route('admin.media.index');
    }

    public function destroy(Media $media): RedirectResponse
    {
        Gate::authorize('delete', $media);

        $media->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('File deleted.')]);

        return back();
    }

    /**
     * @return QueryBuilder<Media>
     */
    protected function query(): QueryBuilder
    {
        return QueryBuilder::for(Media::class)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $query, string $search) => $query->whereAny(['name', 'file_name'], 'like', "%{$search}%"))->delimiter(''),
                AllowedFilter::callback('type', fn (Builder $query, string $type) => match ($type) {
                    'image' => $query->where('mime_type', 'like', 'image/%'),
                    'document' => $query->where('mime_type', 'not like', 'image/%'),
                    default => $query,
                })->delimiter(''),
                AllowedFilter::exact('collection', 'collection_name'),
            )
            ->defaultSort('-id');
    }
}
