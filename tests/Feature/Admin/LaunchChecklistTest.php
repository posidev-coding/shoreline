<?php

use App\Filament\Widgets\LaunchChecklist;
use App\Models\Page;
use App\Models\User;
use App\Settings\SiteSettings;
use Database\Seeders\ContentSeeder;

it('lists what still needs real details', function () {
    $this->seed(ContentSeeder::class);

    expect(LaunchChecklist::items())->toHaveCount(6)
        ->and(LaunchChecklist::canView())->toBeTrue();
});

it('says when new-lead alerts have no owner to go to', function () {
    expect(LaunchChecklist::items())->toContain('There’s no Owner account yet, so new quote requests alert the super admins. Create one with php artisan app:create-admin <email> --owner.');

    User::factory()->owner()->create();

    expect(implode(' ', LaunchChecklist::items()))->not->toContain('no Owner account');
});

it('flags a quote form whose Turnstile check is off or on test keys', function (?string $key, ?string $secret, string $expected) {
    config(['services.turnstile.key' => $key, 'services.turnstile.secret' => $secret]);

    expect(implode(' ', LaunchChecklist::items()))->toContain($expected);
})->with([
    'no keys' => [null, null, 'Turnstile check is off'],
    'test keys' => ['1x00000000000000000000AA', '1x0000000000000000000000000000000AA', 'uses test keys'],
]);

it('disappears once the details are real and the legal pages are reviewed', function () {
    $this->seed(ContentSeeder::class);
    $settings = app(SiteSettings::class);
    $settings->phone = '+12525551234';
    $settings->hours = [['days' => ['Monday'], 'opens' => '08:00', 'closes' => '17:00']];
    $settings->google_review_url = 'https://g.page/r/example/review';
    $settings->save();
    Page::query()->update(['body' => '<p>Reviewed.</p>']);
    User::factory()->owner()->create();
    config(['services.turnstile.key' => '0x4AAAAAAA', 'services.turnstile.secret' => '0x4AAAAAAA-secret']);

    expect(LaunchChecklist::items())->toBe([])
        ->and(LaunchChecklist::canView())->toBeFalse();
});
