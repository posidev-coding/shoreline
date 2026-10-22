<?php

use App\Models\Service;
use App\Models\Showcase;
use App\Settings\SiteSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(ContentSeeder::class);
});

/**
 * @return list<array<string, mixed>>
 */
function jsonLd(TestResponse $response): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', (string) $response->getContent(), $matches);

    return array_map(fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
}

/**
 * @param  list<array<string, mixed>>  $schemas
 * @return array<string, mixed>|null
 */
function schemaOfType(array $schemas, string $type): ?array
{
    return collect($schemas)->firstWhere('@type', $type);
}

it('gives every page a title, description, canonical URL, and social image', function () {
    $this->get('/services/garage-door-repair')
        ->assertSee('<title>Garage Door Repair in Morehead City, NC | Shoreline Doors</title>', false)
        ->assertSee('<meta name="description"', false)
        ->assertSee('<link rel="canonical" href="'.url('/services/garage-door-repair').'">', false)
        ->assertSee('<meta property="og:image" content="'.asset('images/og/default.png').'">', false);
});

it('drops the query string from canonical URLs', function () {
    $this->get('/our-work?service=garage-door-repair&utm_source=flyer')
        ->assertSee('<link rel="canonical" href="'.url('/our-work').'">', false);
});

it('describes the business on every page, serving every published town', function () {
    $business = schemaOfType(jsonLd($this->get('/about')), 'HomeAndConstructionBusiness');

    expect($business)->not->toBeNull()
        ->and($business['name'])->toBe('Shoreline Doors')
        ->and($business['address']['addressLocality'])->toBe('Morehead City')
        ->and($business['areaServed'])->toHaveCount(10)
        ->and(collect($business['areaServed'])->pluck('name'))->toContain('Swansboro, NC', 'Havelock, NC');
});

it('keeps the placeholder phone number out of the structured data', function () {
    expect(schemaOfType(jsonLd($this->get('/')), 'HomeAndConstructionBusiness'))->not->toHaveKey('telephone');

    $settings = app(SiteSettings::class);
    $settings->phone = '+12525551234';
    $settings->save();

    expect(schemaOfType(jsonLd($this->get('/')), 'HomeAndConstructionBusiness')['telephone'])->toBe('+12525551234');
});

it('marks up service pages as a Service with breadcrumbs', function () {
    $schemas = jsonLd($this->get('/services/springs-and-cables'));

    expect(schemaOfType($schemas, 'Service'))
        ->name->toBe('Springs & Cables')
        ->provider->toBe(['@type' => 'HomeAndConstructionBusiness', '@id' => url('/').'#business'])
        ->and(collect(schemaOfType($schemas, 'BreadcrumbList')['itemListElement'])->pluck('name')->all())
        ->toBe(['Home', 'Services', 'Springs & Cables']);
});

it('lists published pages in the sitemap and nothing unpublished', function () {
    Service::query()->where('slug', 'springs-and-cables')->update(['is_published' => false]);
    $published = Showcase::factory()->create();
    $draft = Showcase::factory()->draft()->create();

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/xml; charset=UTF-8')
        ->assertSee(url('/services/garage-door-repair'))
        ->assertSee(url('/service-areas/beaufort'))
        ->assertSee(url('/privacy'))
        ->assertSee(route('work.show', $published))
        ->assertDontSee(url('/services/springs-and-cables'))
        ->assertDontSee(route('work.show', $draft));
});

it('keeps crawlers out everywhere but production', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee("User-agent: *\nDisallow: /", false)
        ->assertDontSee('Sitemap:');

    app()->detectEnvironment(fn () => 'production');

    $this->get('/robots.txt')
        ->assertSee('Disallow: /admin')
        ->assertSee('Sitemap: '.url('/sitemap.xml'));
});
