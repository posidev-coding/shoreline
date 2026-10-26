<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Imagick;
use ImagickException;

/**
 * Converts an iPhone HEIC (or HEIF) photo to JPEG with Imagick, so every browser can show it.
 *
 * Imagick can list HEIC as a format and still fail to decode one: on Ubuntu, libheif's HEVC
 * decoder is a separate plugin (libheif-plugin-libde265). So a failed conversion returns null,
 * and the caller keeps the original.
 */
class HeicConverter
{
    /** Brands in an ISO media file's ftyp box that mark a HEIC or HEIF still image. */
    private const array BRANDS = ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'mif1', 'msf1'];

    /**
     * Sniffs the file itself, since browsers report a HEIC's type inconsistently (or not at all).
     */
    public function isHeic(string $path): bool
    {
        $header = @file_get_contents($path, length: 12);

        return is_string($header)
            && strlen($header) === 12
            && substr($header, 4, 4) === 'ftyp'
            && in_array(substr($header, 8, 4), self::BRANDS, true);
    }

    /**
     * Writes a JPEG next to the file and returns its path, or null when Imagick can't decode it.
     */
    public function toJpeg(string $path): ?string
    {
        try {
            $image = new Imagick($path);
            $image->setIteratorIndex(0);
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(85);
            // libheif has already applied the photo's rotation; don't let viewers apply it again.
            $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
            $image->writeImage($jpeg = $path.'.jpg');
            $image->clear();

            return $jpeg;
        } catch (ImagickException $exception) {
            Log::warning('Couldn’t convert a HEIC photo to JPEG; keeping the original.', ['error' => $exception->getMessage()]);

            return null;
        }
    }
}
