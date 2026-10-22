<?php

namespace App\Models;

use Database\Factories\ShowcaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A public before/after pair for the Our Work gallery.
 *
 * The uploaded originals stay on the private disk, since they can carry EXIF GPS
 * coordinates of a customer's home. Only re-encoded WebP sizes, which carry no
 * metadata, go to the public_media disk that visitors load.
 */
#[Fillable(['title', 'slug', 'service_id', 'service_area_id', 'story', 'has_photo_release', 'is_featured', 'published_at'])]
class Showcase extends Model implements HasMedia
{
    /** @use HasFactory<ShowcaseFactory> */
    use HasFactory, InteractsWithMedia;

    /** Widths of the public WebP renditions, smallest first. */
    public const array WIDTHS = [640, 1280, 1920];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<ServiceArea, $this>
     */
    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    public function registerMediaCollections(): void
    {
        foreach (['before', 'after'] as $collection) {
            $this->addMediaCollection($collection)
                ->singleFile()
                ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->useDisk('private')
                ->storeConversionsOnDisk('public_media');
        }
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        foreach (self::WIDTHS as $width) {
            $this->addMediaConversion("w{$width}")
                ->performOnCollections('before', 'after')
                ->fit(Fit::Max, $width, $width * 2)
                ->format('webp')
                ->quality(80);
        }
    }

    /**
     * Whether visitors can see it: published, with the customer's photo release on file.
     */
    public function isPublic(): bool
    {
        return $this->has_photo_release && $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('has_photo_release', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Featured first, then newest.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByDesc('is_featured')->orderByDesc('published_at');
    }

    protected function casts(): array
    {
        return [
            'has_photo_release' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
