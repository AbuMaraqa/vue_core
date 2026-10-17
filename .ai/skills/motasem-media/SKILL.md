---
name: motasem-media
description: 'The file/image upload pipeline of this project: the FilePond-based MediaUpload Vue component, TemporaryUpload model and controller, the MediaInput {uploads, library, removed} payload, ValidatesMediaInput/ValidTemporaryUpload validation, the SyncMediaCollection action, Spatie Media Library collections, the Site model and the media library page/picker. Activate whenever adding an image, logo, gallery, attachment or file field to any model or form, touching media/uploads code, or when the user mentions صور، رفع ملفات، مكتبة الوسائط، upload, image, file, media.'
---

# Media & Uploads

The pipeline works like Filament's `SpatieMediaLibraryFileUpload`:

1. Files upload to a temporary location as soon as they are dropped.
2. The form sends only _ids_.
3. The server attaches the files when the form is saved.

## Data flow

```
MediaUpload.vue (FilePond)
  ├─ drop file ──► POST admin.uploads.store ─► TemporaryUpload row + file on the `local` disk
  │                (throttle 60/min)           returns { id: uuid }
  ├─ undo ───────► DELETE admin.uploads.destroy (only the owner, else 404)
  ├─ "choose from library" ─► MediaPicker ─► GET admin.media.browse (images only, paginated)
  └─ v-model: MediaInput = { uploads: string[], library: number[], removed: number[] }
                                     │ form submit
                                     ▼
FormRequest uses ValidatesMediaInput::mediaRules('logo', $mimeTypes, maxKilobytes, maxFiles)
  └─ ValidTemporaryUpload: the upload exists, belongs to the user, and matches type and size
                                     ▼
SyncMediaCollection::handle($model, $collection, MediaInputData::from($validated['logo'] ?? []))
  1. delete the `removed` media of that collection
  2. copy the `library` media into the collection
  3. attach the user's `uploads` (original file name kept) and delete the temporary rows
```

- Unused temporary uploads are pruned after a day: `model:prune` runs hourly in `routes/console.php`.
- Deleting a `TemporaryUpload` deletes its directory.

## Adding an image field to a model

**1. Model:** implement `HasMedia`, use `InteractsWithMedia`, and declare the collection with constants:

```php
public const string IMAGE = 'image';

public function registerMediaCollections(): void
{
    $this->addMediaCollection(self::IMAGE)->singleFile()->acceptsMimeTypes(Site::IMAGE_MIME_TYPES);
}
```

- `Site::IMAGE_MIME_TYPES` = png, jpeg, webp, gif. SVG is excluded on purpose because it can carry scripts.
- Drop `singleFile()` for galleries.

**2. FormRequest:**

```php
use ValidatesMediaInput;

'...' => ...,
...$this->mediaRules('image', Site::IMAGE_MIME_TYPES, maxKilobytes: 2048, maxFiles: 1),
```

**3. Action/controller (after saving the model):**

```php
$syncMedia->handle($category, Category::IMAGE, MediaInputData::from($request->validated('image', [])));
```

Run it inside the same flow as the model save. Inject `SyncMediaCollection` as a dependency.

**4. Resource:** expose the current media with `MediaResource`, e.g. `'image' => $this->getFirstMedia(Category::IMAGE) ? MediaResource::make(...) : null`. Use `MediaResource::collection($this->getMedia(...))` for galleries.

**5. Vue form:**

```vue
<MediaUpload
    v-model="form.image"
    v-model:busy="imageUploading"
    :media="category?.image ? [category.image] : []"
    max-file-size="2MB"
/>
<BaseInputError :message="imageError" />
```

- Initialise `image: { uploads: [], library: [], removed: [] } as MediaInput` in `useForm`.
- Disable submit while `busy`.
- Media errors arrive under nested keys (`image.uploads.0`). Find them with `Object.entries(form.errors).find(([f]) => f.startsWith('image'))`, as `pages/admin/settings/Edit.vue` does.
- After a successful save, reset the field. Bump a `:key` to remount FilePond (`logoFieldKey` in settings).

**MediaUpload props:**

| Prop                   | Meaning                                                           |
| ---------------------- | ----------------------------------------------------------------- |
| `media`                | existing `MediaItem[]`                                            |
| `acceptedFileTypes`    | defaults to the image types                                       |
| `maxFileSize`          | e.g. `'10MB'`                                                     |
| `multiple`, `maxFiles` | gallery mode and its limit                                        |
| `allowLibrary`         | defaults to true; the library button also needs `Media View List` |
| `disabled`             | disables the field                                                |

It emits `processed`.

## The Site model and the media library page

- `App\Modules\Core\Models\Site` is a single-row model (`Site::current()` creates it on first use). It owns site-wide media:
    - `Site::LOGO` (single file, shown via the `theme.logoUrl` prop);
    - `Site::LIBRARY` (files uploaded directly on the media page).
- `MediaController` (`admin/media/Index`) lists **every** media row of every model, filterable by search, type (image/document) and collection. Uploading there goes to `Site::LIBRARY` (`StoreMediaRequest`: 1 to 20 uploads, 10 MB each). Delete uses `MediaPolicy`.
- `MediaResource` returns `id`, `name`, `file_name`, `mime_type`, `is_image`, `size`, `human_size`, `url`, `collection`, `owner {type, id}`, `created_at` and `can.delete`. Its TS mirror is `MediaItem` (`types/media.ts`).

## Testing media

```php
beforeEach(function () {
    Storage::fake('local');   // temporary uploads
    Storage::fake('public');  // media library
});

$upload = TemporaryUpload::factory()->image('brand.png')->create(['user_id' => $staffMember->id]);
$this->actingAs($staffMember)->put(route('admin.settings.update'), [..., 'logo' => ['uploads' => [$upload->id]]]);
```

Cover these cases:

- someone else's upload id is rejected;
- a wrong mime type is rejected;
- library ids are copied, not moved;
- `removed` deletes.

See `tests/Feature/Modules/Core/{MediaControllerTest,TemporaryUploadControllerTest,SettingControllerTest}.php`.
