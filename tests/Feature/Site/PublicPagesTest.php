<?php

use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\Showcase;
use App\Models\Testimonial;
use Database\Seeders\ContentSeeder;

beforeEach(function () {
    $this->seed(ContentSeeder::class);
});

it('renders every public page', function (string $url, string $text) {
    $this->get($url)->assertOk()->assertSee($text);
})->with([
    'home' => ['/', 'Up, down & done right.'],
    'services' => ['/services', 'Garage Door Repair'],
    'a service' => ['/services/springs-and-cables', 'Springs & Cables'],
    'our work' => ['/our-work', 'Our Work'],
    'service areas' => ['/service-areas', 'Craven County'],
    'a town' => ['/service-areas/beaufort', 'Garage doors in Beaufort'],
    'reviews' => ['/reviews', 'Reviews'],
    'faq' => ['/faq', 'How do I schedule work?'],
    'hurricane-ready' => ['/hurricane-ready', 'ANSI/DASMA 108'],
    'about' => ['/about', 'About Shoreline Doors'],
    'contact' => ['/contact', 'hello@shorelinedoors.com'],
    'privacy' => ['/privacy', 'QuickBooks'],
    'terms' => ['/terms', 'Terms of Service'],
    'get a quote' => ['/get-a-quote', 'Get a Quote'],
]);

it('fills in the last-updated date on the legal pages', function () {
    $this->get('/privacy')
        ->assertDontSee('{{ last_updated }}', false)
        ->assertSee(now()->format('F j, Y'));
});

it('hides unpublished services and towns', function () {
    Service::query()->where('slug', 'springs-and-cables')->update(['is_published' => false]);
    ServiceArea::query()->where('slug', 'beaufort')->update(['is_published' => false]);

    $this->get('/services/springs-and-cables')->assertNotFound();
    $this->get('/service-areas/beaufort')->assertNotFound();
    $this->get('/services')->assertDontSee('Springs & Cables');
});

it('shows a job only once it is published with a photo release', function () {
    $published = Showcase::factory()->create(['title' => 'Published job']);
    $draft = Showcase::factory()->draft()->create(['title' => 'Draft job']);
    $noRelease = Showcase::factory()->withoutPhotoRelease()->create(['title' => 'No release job']);
    $scheduled = Showcase::factory()->create(['title' => 'Scheduled job', 'published_at' => now()->addDay()]);

    $this->get('/our-work')
        ->assertSee('Published job')
        ->assertDontSee('Draft job')
        ->assertDontSee('No release job')
        ->assertDontSee('Scheduled job');

    $this->get(route('work.show', $published))->assertOk();
    $this->get(route('work.show', $draft))->assertNotFound();
    $this->get(route('work.show', $noRelease))->assertNotFound();
    $this->get(route('work.show', $scheduled))->assertNotFound();
});

it('shows only published reviews', function () {
    Testimonial::factory()->create(['author_name' => 'Pat S.']);
    Testimonial::factory()->unpublished()->create(['author_name' => 'Hidden H.']);

    $this->get('/reviews')->assertSee('Pat S.')->assertDontSee('Hidden H.');
});

it('gives each town its own jobs and reviews', function () {
    $beaufort = ServiceArea::query()->where('slug', 'beaufort')->firstOrFail();
    $emeraldIsle = ServiceArea::query()->where('slug', 'emerald-isle')->firstOrFail();
    Showcase::factory()->for($beaufort, 'serviceArea')->create(['title' => 'Front Street door']);
    Showcase::factory()->for($emeraldIsle, 'serviceArea')->create(['title' => 'Oceanfront rental door']);
    Testimonial::factory()->for($beaufort, 'serviceArea')->create(['author_name' => 'Beaufort B.']);
    Testimonial::factory()->for($emeraldIsle, 'serviceArea')->create(['author_name' => 'Island I.']);

    $this->get('/service-areas/beaufort')
        ->assertSee('Front Street door')
        ->assertSee('Beaufort B.')
        ->assertDontSee('Oceanfront rental door')
        ->assertDontSee('Island I.');
});

it('filters our work by service and town, ignoring unknown values', function () {
    $repair = Service::query()->where('slug', 'garage-door-repair')->firstOrFail();
    $install = Service::query()->where('slug', 'garage-door-installation')->firstOrFail();
    Showcase::factory()->for($repair)->create(['title' => 'Repair job']);
    Showcase::factory()->for($install)->create(['title' => 'Install job']);

    $this->get('/our-work?service=garage-door-repair')->assertSee('Repair job')->assertDontSee('Install job');
    $this->get('/our-work?service=not-a-service&town=nowhere')->assertSee('Repair job')->assertSee('Install job');
});

it('prefills the quote page from a service', function () {
    $this->get('/get-a-quote?service=springs-and-cables')
        ->assertOk()
        ->assertSee('Springs & Cables')
        ->assertSee('subject=Quote%20request%3A%20Springs%20%26%20Cables', false);
});

it('scores the hurricane check and adds the answers to the email', function () {
    $this->get('/get-a-quote?'.http_build_query([
        'service' => 'hurricane-wind-rated-doors',
        'age' => 'over-20',
        'label' => 'no',
        'windows' => 'standard',
        'condition' => 'some',
    ]))
        ->assertOk()
        ->assertSee('Have someone look at it before the next storm.')
        ->assertSee(rawurlencode('Can you find a wind-load label on the door? No'), false);
});

it('shows no hurricane result for too few or invalid answers', function () {
    $this->get('/get-a-quote?age=over-20&label=no&windows=sunroof')
        ->assertOk()
        ->assertDontSee('Your hurricane-ready check');
});

it('shows a branded page for addresses that don’t exist', function () {
    $this->get('/no-such-page')->assertNotFound()->assertSee('Nothing behind this door');
});
