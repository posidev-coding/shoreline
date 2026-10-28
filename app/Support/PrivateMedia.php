<?php

namespace App\Support;

use DateTimeInterface;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Short-lived links to files on the private disk, for the admin and the portal alike.
 */
final class PrivateMedia
{
    public const int LINK_MINUTES = 30;

    /**
     * The types every browser shows inline. A HEIC that wouldn't convert isn't one of them.
     */
    public const array BROWSER_IMAGES = ['image/jpeg', 'image/png', 'image/webp'];

    public static function expiry(): DateTimeInterface
    {
        return now()->addMinutes(self::LINK_MINUTES);
    }

    public static function url(Media $media, ?DateTimeInterface $expires = null): string
    {
        return $media->getTemporaryUrl($expires ?? self::expiry());
    }

    /**
     * The thumbnail once it's generated (conversions are queued), else the photo itself when a
     * browser can show it, else nothing.
     */
    public static function thumbnailUrl(Media $media, ?DateTimeInterface $expires = null): ?string
    {
        $expires ??= self::expiry();

        if ($media->hasGeneratedConversion('thumb')) {
            return $media->getTemporaryUrl($expires, 'thumb');
        }

        return in_array($media->mime_type, self::BROWSER_IMAGES, true) ? $media->getTemporaryUrl($expires) : null;
    }
}
