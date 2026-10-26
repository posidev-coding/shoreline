<?php

use App\Filament\Widgets\LaunchChecklist;
use App\Models\Page;
use App\Settings\SiteSettings;
use Database\Seeders\ContentSeeder;

it('lists what still needs real details', function () {
    $this->seed(ContentSeeder::class);

    expect(LaunchChecklist::items())->toHaveCount(4)
        ->and(LaunchChecklist::canView())->toBeTrue();
});

it('disappears once the details are real and the legal pages are reviewed', function () {
    $this->seed(ContentSeeder::class);
    $settings = app(SiteSettings::class);
    $settings->phone = '+12525551234';
    $settings->hours = [['days' => ['Monday'], 'opens' => '08:00', 'closes' => '17:00']];
    $settings->google_review_url = 'https://g.page/r/example/review';
    $settings->save();
    Page::query()->update(['body' => '<p>Reviewed.</p>']);

    expect(LaunchChecklist::items())->toBe([])
        ->and(LaunchChecklist::canView())->toBeFalse();
});
