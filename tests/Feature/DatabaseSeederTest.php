<?php

use App\Models\Lead;
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
        ->and(Lead::query()->count())->toBe(3)
        // Showcases' before and after, the leads' photos, and the spring job's two photos and warranty.
        ->and(Media::query()->count())->toBe(11)
        ->and(Media::query()->whereNull('uuid')->count())->toBe(0);
});
