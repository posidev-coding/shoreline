<?php

use App\Models\Showcase;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;

beforeEach(function () {
    Storage::fake('private');
    Storage::fake('public_media');
});

it('keeps original photos private and publishes only resized WebP copies', function () {
    $showcase = Showcase::factory()->create();

    $media = $showcase->addMedia(base_path('tests/Fixtures/door-with-gps.jpg'))
        ->preservingOriginal()
        ->toMediaCollection('after');

    expect($media->disk)->toBe('private')
        ->and($media->conversions_disk)->toBe('public_media');
    Storage::disk('private')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public_media')->assertMissing($media->getPathRelativeToRoot());

    foreach (Showcase::WIDTHS as $width) {
        expect($media->refresh()->hasGeneratedConversion("w{$width}"))->toBeTrue();
        Storage::disk('public_media')->assertExists($media->getPathRelativeToRoot("w{$width}"));
        expect($media->getPathRelativeToRoot("w{$width}"))->toEndWith('.webp');
    }
});

it('strips the location from public photos', function () {
    $fixture = base_path('tests/Fixtures/door-with-gps.jpg');
    expect(exif_read_data($fixture))->toHaveKey('GPSLatitude');

    $media = Showcase::factory()->create()
        ->addMedia($fixture)
        ->preservingOriginal()
        ->toMediaCollection('before');

    $public = Storage::disk('public_media')->get($media->getPathRelativeToRoot('w640'));

    // WebP stores metadata in an "EXIF" chunk; GD writes none, so neither marker may appear.
    expect($public)->not->toContain('EXIF')
        ->not->toContain('Exif')
        ->and(Storage::disk('private')->get($media->getPathRelativeToRoot()))->toContain('Exif');
});

it('only accepts JPEG, PNG, and WebP photos', function () {
    $showcase = Showcase::factory()->create();
    $text = tempnam(sys_get_temp_dir(), 'not-a-photo');
    file_put_contents($text, 'not an image');

    expect(fn () => $showcase->addMedia($text)->toMediaCollection('after'))
        ->toThrow(FileUnacceptableForCollection::class);
});
