<?php

use App\Jobs\AttachLeadPhotos;
use App\Models\Lead;
use App\Support\HeicConverter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Whether this machine's Imagick can decode HEVC. On Ubuntu that needs libheif-plugin-libde265.
 * (It runs before the app boots, so no base_path().)
 */
function canDecodeHeic(): bool
{
    try {
        return (new Imagick(__DIR__.'/../Fixtures/door.heic'))->getImageWidth() > 0;
    } catch (ImagickException) {
        return false;
    }
}

beforeEach(function () {
    Storage::fake('private');
    Storage::fake('uploads');
    $this->lead = Lead::factory()->create();
});

/**
 * Puts a fixture where Livewire would have left the upload, and returns the job's entry for it.
 *
 * @return array{path: string, name: string}
 */
function upload(string $fixture, string $name): array
{
    Storage::disk('uploads')->put($path = 'livewire-tmp/'.Str::random(20), file_get_contents(base_path("tests/Fixtures/{$fixture}")));

    return ['path' => $path, 'name' => $name];
}

it('attaches a JPEG with a thumbnail, and clears the temporary upload', function () {
    $upload = upload('door-with-gps.jpg', 'IMG_0412.JPG');

    (new AttachLeadPhotos($this->lead, 'uploads', [$upload]))->handle(app(HeicConverter::class));

    $photo = $this->lead->getFirstMedia('photos');
    expect($photo)
        ->name->toBe('IMG_0412')
        ->file_name->toBe('img-0412.jpg')
        ->mime_type->toBe('image/jpeg')
        ->disk->toBe('private')
        ->and($photo->hasGeneratedConversion('thumb'))->toBeTrue()
        ->and(Storage::disk('uploads')->exists($upload['path']))->toBeFalse();
});

it('converts an iPhone HEIC photo to JPEG', function () {
    dispatch_sync(new AttachLeadPhotos($this->lead, 'uploads', [upload('door.heic', 'IMG_0413.HEIC')]));

    expect($this->lead->getFirstMedia('photos'))
        ->file_name->toBe('img-0413.jpg')
        ->mime_type->toBe('image/jpeg');
})->skip(! canDecodeHeic(), 'Imagick here can’t decode HEVC. On Ubuntu, install libheif-plugin-libde265.');

it('keeps the original HEIC when it won’t convert', function () {
    Log::spy();

    dispatch_sync(new AttachLeadPhotos($this->lead, 'uploads', [upload('door-corrupt.heic', 'IMG_0414.HEIC')]));

    expect($this->lead->getFirstMedia('photos'))
        ->file_name->toBe('img-0414.heic')
        ->mime_type->toBe('image/heic');
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'HEIC'));
});

it('drops an upload that isn’t really an image, whatever its name says', function () {
    $upload = ['path' => 'livewire-tmp/fake', 'name' => 'door.jpg'];
    Storage::disk('uploads')->put($upload['path'], '<html><script>alert(1)</script></html>');

    dispatch_sync(new AttachLeadPhotos($this->lead, 'uploads', [$upload]));

    expect($this->lead->getMedia('photos'))->toBeEmpty()
        ->and(Storage::disk('uploads')->exists($upload['path']))->toBeFalse();
});

it('skips uploads an earlier attempt already attached', function () {
    Log::spy();
    $this->lead->addMedia(base_path('tests/Fixtures/door-with-gps.jpg'))->preservingOriginal()
        ->usingName('first')->withCustomProperties(['upload' => 'livewire-tmp/already-attached'])->toMediaCollection('photos');
    $done = ['path' => 'livewire-tmp/already-attached', 'name' => 'first.jpg'];

    dispatch_sync(new AttachLeadPhotos($this->lead, 'uploads', [$done, upload('door-with-gps.jpg', 'second.jpg')]));

    expect($this->lead->refresh()->getMedia('photos')->pluck('name')->all())->toBe(['first', 'second']);
    Log::shouldNotHaveReceived('error');
});

it('logs an upload that was gone before anything attached it', function () {
    Log::spy();

    dispatch_sync(new AttachLeadPhotos($this->lead, 'uploads', [['path' => 'livewire-tmp/vanished', 'name' => 'door.jpg']]));

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context) => str_contains($message, 'gone') && $context['path'] === 'livewire-tmp/vanished');
});

it('attaches the other photos when the media library refuses one', function () {
    // On staging, a 12 MB phone photo met the media library's 10 MB default, and the job lost every photo.
    Log::spy();
    config(['media-library.max_file_size' => 10 * 1024]);
    $big = UploadedFile::fake()->image('big.jpg', 3000, 3000);
    expect($big->getSize())->toBeGreaterThan(10 * 1024);
    Storage::disk('uploads')->put($bigPath = 'livewire-tmp/big', file_get_contents($big->getRealPath()));

    dispatch_sync(new AttachLeadPhotos($this->lead, 'uploads', [
        ['path' => $bigPath, 'name' => 'IMG_0001.JPG'],
        upload('door-with-gps.jpg', 'IMG_0002.JPG'),
    ]));

    expect($this->lead->getMedia('photos')->pluck('name')->all())->toBe(['IMG_0002'])
        ->and(Storage::disk('uploads')->allFiles())->toBe([]);
    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context) => str_contains($message, 'refused') && $context['name'] === 'IMG_0001.JPG');
});

it('scales a large converted HEIC down to a 12 MP photo', function () {
    copy(base_path('tests/Fixtures/door.heic'), $heic = sys_get_temp_dir().'/'.Str::random(8).'.heic');

    $jpeg = (new HeicConverter)->toJpeg($heic, maxEdge: 160);

    expect((new Imagick((string) $jpeg))->getImageGeometry())->toBe(['width' => 160, 'height' => 120]);
    unlink($heic);
    unlink((string) $jpeg);
})->skip(! canDecodeHeic(), 'Imagick here can’t decode HEVC. On Ubuntu, install libheif-plugin-libde265.');

it('keeps every photo size limit in step, so a photo one accepts the next never refuses', function () {
    $bytes = (int) config('media-library.max_file_size');

    expect(Lead::maxPhotoKilobytes() * 1024)->toBe($bytes)
        ->and(config('livewire.temporary_file_upload.rules'))->toContain('max:'.intdiv($bytes, 1024))
        // Phones save photos near 12 MB.
        ->and($bytes)->toBeGreaterThanOrEqual(20 * 1024 * 1024);
});
