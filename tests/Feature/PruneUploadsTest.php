<?php

use Illuminate\Support\Facades\Storage;

it('deletes temporary uploads older than a day and keeps recent ones', function () {
    // Under test, Livewire keeps temporary uploads on this disk.
    $storage = Storage::fake('tmp-for-tests');
    $storage->put('livewire-tmp/abandoned.jpg', 'old');
    $storage->put('livewire-tmp/abandoned.jpg.json', '{}');
    $storage->put('livewire-tmp/in-progress.jpg', 'new');
    touch($storage->path('livewire-tmp/abandoned.jpg'), now()->subHours(25)->getTimestamp());
    touch($storage->path('livewire-tmp/abandoned.jpg.json'), now()->subHours(25)->getTimestamp());

    $this->artisan('app:prune-uploads')
        ->expectsOutput('Deleted 2 abandoned uploads.')
        ->assertSuccessful();

    expect($storage->allFiles())->toBe(['livewire-tmp/in-progress.jpg']);
});
