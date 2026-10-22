<?php

use App\Enums\PageKey;
use App\Enums\ServiceIcon;
use App\Filament\Pages\ManageSite;
use App\Filament\Resources\Faqs\Pages\ListFaqs;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\ServiceAreas\Pages\ListServiceAreas;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Filament\Resources\Showcases\Pages\CreateShowcase;
use App\Filament\Resources\Showcases\Pages\EditShowcase;
use App\Filament\Resources\Showcases\Pages\ListShowcases;
use App\Filament\Resources\Testimonials\Pages\ListTestimonials;
use App\Models\Page;
use App\Models\Service;
use App\Models\Showcase;
use App\Models\User;
use App\Settings\SiteSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ContentSeeder::class);
    $this->actingAs(User::factory()->owner()->withMfa()->create());
});

it('lists every kind of website content', function (string $page) {
    Livewire::test($page)->assertOk();
})->with([ListServices::class, ListServiceAreas::class, ListShowcases::class, ListTestimonials::class, ListFaqs::class, ListPages::class]);

it('creates a service, filling in the slug from the name', function () {
    Livewire::test(CreateService::class)
        ->fillForm([
            'name' => 'Gate Repair',
            'icon' => ServiceIcon::Repair,
            'summary' => 'Gates that sag, stick, or won’t latch.',
            'body' => '<p>We fix gates.</p>',
        ])
        ->assertSchemaStateSet(['slug' => 'gate-repair'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Service::query()->where('slug', 'gate-repair')->exists())->toBeTrue();
});

it('won’t publish a job without the customer’s photo release', function () {
    $showcase = Showcase::factory()->withoutPhotoRelease()->draft()->create();

    Livewire::test(EditShowcase::class, ['record' => $showcase->getRouteKey()])
        ->fillForm(['published_at' => now()->subMinute()])
        ->call('save')
        ->assertHasFormErrors(['published_at']);

    expect($showcase->refresh()->published_at)->toBeNull();
});

it('saves an existing job without uploading its photos again', function () {
    Storage::fake('private');
    Storage::fake('public_media');
    $showcase = Showcase::factory()->create();
    foreach (['before', 'after'] as $collection) {
        $showcase->addMedia(base_path('tests/Fixtures/door-with-gps.jpg'))->preservingOriginal()->toMediaCollection($collection);
    }

    Livewire::test(EditShowcase::class, ['record' => $showcase->getRouteKey()])
        ->fillForm(['title' => 'Renamed job'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($showcase->refresh())
        ->title->toBe('Renamed job')
        ->and($showcase->getMedia('before'))->toHaveCount(1)
        ->and($showcase->getMedia('after'))->toHaveCount(1);
});

it('requires before and after photos for a new job', function () {
    Livewire::test(CreateShowcase::class)
        ->fillForm(['title' => 'New job', 'slug' => 'new-job'])
        ->call('create')
        ->assertHasFormErrors(['before' => 'required', 'after' => 'required']);
});

it('edits the fixed pages but never adds or removes one', function () {
    $about = Page::for(PageKey::About);

    Livewire::test(EditPage::class, ['record' => $about->getRouteKey()])
        ->fillForm(['title' => 'About us'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($about->refresh()->title)->toBe('About us')
        ->and(auth()->user()->can('create', Page::class))->toBeFalse()
        ->and(auth()->user()->can('delete', $about))->toBeFalse();
    $this->get('/admin/pages/create')->assertNotFound();
});

it('saves business details with the phone number in E.164', function () {
    Livewire::test(ManageSite::class)
        ->fillForm(['phone' => '(252) 555-1234', 'hours' => [['days' => ['Monday', 'Tuesday'], 'opens' => '08:00', 'closes' => '17:00']]])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(SiteSettings::class)->refresh();
    expect($settings->phone)->toBe('+12525551234')
        ->and($settings->phoneForDisplay())->toBe('(252) 555-1234')
        ->and($settings->hours)->toBe([['days' => ['Monday', 'Tuesday'], 'opens' => '08:00', 'closes' => '17:00']]);
});

it('shows the saved phone number the way the owner typed it', function () {
    Livewire::test(ManageSite::class)
        ->assertSchemaStateSet(['phone' => '(252) 555-0100']);
});

it('rejects a phone number that isn’t a US number', function () {
    Livewire::test(ManageSite::class)
        ->fillForm(['phone' => '12345'])
        ->call('save')
        ->assertHasFormErrors(['phone']);
});

it('keeps clients out of content management', function () {
    $this->actingAs(User::factory()->withMfa()->create());

    expect(auth()->user()->can('viewAny', Service::class))->toBeFalse()
        ->and(auth()->user()->can('update', Page::for(PageKey::About)))->toBeFalse();
});
