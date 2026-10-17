<?php

namespace App\Modules\Core\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * The store itself. It owns the media that does not belong to another model.
 *
 * @property int $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(SiteFactory::class)]
class Site extends Model implements HasMedia
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory, InteractsWithMedia;

    public const string LOGO = 'logo';

    public const string LIBRARY = 'library';

    /**
     * The image types accepted for the logo and the media library. SVG is left
     * out on purpose because it can carry scripts.
     *
     * @var list<string>
     */
    public const array IMAGE_MIME_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    /**
     * Get the single site record, creating it on first use.
     */
    public static function current(): self
    {
        return static::query()->oldest('id')->firstOrCreate();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::LOGO)
            ->singleFile()
            ->acceptsMimeTypes(self::IMAGE_MIME_TYPES);

        $this->addMediaCollection(self::LIBRARY)
            ->acceptsMimeTypes(self::IMAGE_MIME_TYPES);
    }

    public function logoUrl(): ?string
    {
        return $this->getFirstMedia(self::LOGO)?->getFullUrl();
    }
}
