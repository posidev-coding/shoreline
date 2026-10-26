<?php

use App\Enums\PageKey;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\Testimonial;
use Database\Seeders\ContentSeeder;

it('seeds the services, towns, FAQs, and pages', function () {
    $this->seed(ContentSeeder::class);

    expect(Service::query()->count())->toBe(6)
        ->and(ServiceArea::query()->count())->toBe(10)
        ->and(Faq::query()->whereNull('service_id')->count())->toBe(8)
        ->and(Page::query()->pluck('key')->all())->toEqualCanonicalizing(PageKey::cases())
        // Reviews are never seeded: published reviews must be real.
        ->and(Testimonial::query()->count())->toBe(0);
});

it('never overwrites or duplicates the owner’s edits when run again', function () {
    $this->seed(ContentSeeder::class);
    Service::query()->where('slug', 'garage-door-repair')->update(['summary' => 'Owner’s own words.']);
    Page::query()->where('key', PageKey::About)->update(['title' => 'About us']);
    Faq::query()->whereNull('service_id')->first()?->delete();

    $this->seed(ContentSeeder::class);

    expect(Service::query()->where('slug', 'garage-door-repair')->value('summary'))->toBe('Owner’s own words.')
        ->and(Page::for(PageKey::About)->title)->toBe('About us')
        ->and(Faq::query()->whereNull('service_id')->count())->toBe(7)
        ->and(Service::query()->count())->toBe(6);
});

it('keeps the seeded search snippets within search engines’ limits', function () {
    $this->seed(ContentSeeder::class);

    foreach ([...Service::query()->get(), ...ServiceArea::query()->get()] as $record) {
        expect(mb_strlen((string) $record->seo_title))->toBeLessThanOrEqual(60)
            ->and(mb_strlen((string) $record->seo_description))->toBeLessThanOrEqual(155);
    }
});
