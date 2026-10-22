<?php

use App\Models\Showcase;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

it('seeds local demo photos that the admin’s upload fields can find', function () {
    Storage::fake('private');
    Storage::fake('public_media');
    app()->detectEnvironment(fn (): string => 'local');

    $this->seed();

    // The admin matches stored photos to their upload fields by UUID, which the media
    // library assigns in a model event. Seeding without model events leaves it empty.
    expect(Showcase::query()->count())->toBe(3)
        ->and(Media::query()->count())->toBe(6)
        ->and(Media::query()->whereNull('uuid')->count())->toBe(0);
});
