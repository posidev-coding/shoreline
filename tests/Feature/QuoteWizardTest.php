<?php

use App\Enums\ContactMethod;
use App\Enums\LeadStatus;
use App\Enums\Urgency;
use App\Jobs\AttachLeadPhotos;
use App\Livewire\QuoteWizard;
use App\Mail\QuoteRequestReceived;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\NewLead;
use Database\Seeders\ContentSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use RyanChandler\LaravelCloudflareTurnstile\Facades\Turnstile;

beforeEach(function () {
    $this->seed(ContentSeeder::class);
    Mail::fake();
    Notification::fake();
    Queue::fake();
    RateLimiter::clear('quote-requests:127.0.0.1');
});

/**
 * A wizard filled in through the review step, for a garage door repair in Beaufort.
 *
 * @param  array<string, mixed>  $overrides
 */
function completedWizard(array $overrides = []): Testable
{
    $fields = [
        'service' => 'garage-door-repair',
        'doorSize' => 'double',
        'symptoms' => ['spring', 'wont-move'],
        'urgency' => Urgency::Emergency->value,
        'message' => 'Heard a bang this morning.',
        'name' => 'Jane Smith',
        'phone' => '(252) 555-0142',
        'email' => 'Jane.Smith@Example.com',
        'preferredContact' => ContactMethod::Text->value,
        'smsConsent' => true,
        'street' => '12 Front St',
        'town' => 'beaufort',
        'zip' => '28516',
        ...$overrides,
    ];

    $wizard = Livewire::test(QuoteWizard::class);

    foreach ($fields as $name => $value) {
        $wizard->set($name, $value);
    }

    // The honeypot rejects a form sent faster than a person could fill it in.
    test()->travel(5)->seconds();

    return $wizard->set('step', QuoteWizard::REVIEW);
}

it('shows the wizard on the quote page', function () {
    $this->get('/get-a-quote')
        ->assertOk()
        ->assertSeeLivewire(QuoteWizard::class)
        ->assertSee('What do you need?');
});

it('defers Livewire’s script, so it doesn’t hold up the page’s first paint', function () {
    expect($this->get('/get-a-quote')->getContent())->toMatch('/<script src="[^"]*livewire[^"]*"[^>]*\bdefer\b/');
});

it('starts at the details when a service page links to it', function () {
    $this->get('/get-a-quote?service=springs-and-cables')
        ->assertOk()
        ->assertSee('What’s going on?', false);
});

it('creates the lead, alerts the owner, and confirms to the customer', function () {
    $owner = User::factory()->owner()->create();

    completedWizard()
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('quote.thanks'));

    $lead = Lead::query()->sole();
    expect($lead)
        ->status->toBe(LeadStatus::New)
        ->email->toBe('jane.smith@example.com')
        ->phone->toBe('+12525550142')
        ->town->toBe('Beaufort')
        ->serviceArea->slug->toBe('beaufort')
        ->service->slug->toBe('garage-door-repair')
        ->urgency->toBe(Urgency::Emergency)
        ->sms_consent->toBeTrue()
        ->marketing_consent->toBeFalse()
        // jsonb keeps its own key order.
        ->answers->toEqual(['door_size' => 'double', 'symptoms' => ['spring', 'wont-move']]);

    Notification::assertSentTo($owner, NewLead::class, fn (NewLead $notification) => $notification->lead->is($lead));
    Mail::assertQueued(QuoteRequestReceived::class, fn (QuoteRequestReceived $mail) => $mail->hasTo('jane.smith@example.com'));
    Queue::assertNotPushed(AttachLeadPhotos::class);
});

it('hands the photos to a queued job', function () {
    $wizard = completedWizard()->set('photos', [
        UploadedFile::fake()->image('door.jpg'),
        UploadedFile::fake()->image('springs.png'),
    ]);

    $wizard->call('submit')->assertHasNoErrors();

    Queue::assertPushed(AttachLeadPhotos::class, fn (AttachLeadPhotos $job) => count($job->uploads) === 2
        && $job->uploads[0]['name'] === 'door.jpg'
        && str_starts_with($job->uploads[0]['path'], 'livewire-tmp/'));
});

it('keeps the photos that pass and explains the ones that don’t', function () {
    Livewire::test(QuoteWizard::class)
        ->set('step', QuoteWizard::PHOTOS)
        ->set('photos', [UploadedFile::fake()->image('door.jpg'), UploadedFile::fake()->create('invoice.pdf', 20, 'application/pdf')])
        ->assertHasErrors('photos')
        ->assertCount('photos', 1)
        ->assertSee('We couldn’t add invoice.pdf', false);
});

it('takes six photos at most', function () {
    $photos = array_map(fn (int $n) => UploadedFile::fake()->image("photo-{$n}.jpg"), range(1, 8));

    Livewire::test(QuoteWizard::class)
        ->set('photos', $photos)
        ->assertCount('photos', Lead::MAX_PHOTOS)
        ->assertHasErrors('photos');
});

it('lets someone remove a photo', function () {
    Livewire::test(QuoteWizard::class)
        ->set('photos', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')])
        ->call('removePhoto', 0)
        ->assertCount('photos', 1)
        ->assertSet('photos', fn (array $photos) => $photos[0] instanceof TemporaryUploadedFile && $photos[0]->getClientOriginalName() === 'b.jpg');
});

it('asks only what the service needs before moving on', function (string $service, array $required, array $notRequired) {
    Livewire::test(QuoteWizard::class)
        ->set('service', $service)
        ->set('step', QuoteWizard::DETAILS)
        ->call('next')
        ->assertHasErrors($required)
        ->assertHasNoErrors($notRequired)
        ->assertSet('step', QuoteWizard::DETAILS);
})->with([
    'a repair needs the door and urgency' => ['garage-door-repair', ['doorSize', 'urgency'], ['message']],
    'handyman work needs a description' => ['handyman-home-repairs', ['message', 'urgency'], ['doorSize']],
    'something else needs a description' => ['other', ['message', 'urgency'], ['doorSize']],
]);

it('saves only the answers the chosen service asks for', function () {
    // They picked a door size and symptoms, then switched to handyman work.
    completedWizard(['service' => 'handyman-home-repairs'])->call('submit')->assertHasNoErrors();

    expect(Lead::query()->sole()->answers)->toBe([]);
});

it('needs consent before texting someone', function () {
    completedWizard(['smsConsent' => false])
        ->call('submit')
        ->assertHasErrors('smsConsent')
        ->assertSet('step', QuoteWizard::CONTACT);

    expect(Lead::query()->count())->toBe(0);
});

it('takes a town outside the service areas', function () {
    completedWizard(['town' => 'other', 'otherTown' => 'Harkers Island', 'zip' => '28531'])
        ->call('submit')
        ->assertHasNoErrors();

    expect(Lead::query()->sole())
        ->town->toBe('Harkers Island')
        ->service_area_id->toBeNull();
});

it('carries the hurricane-ready answers into the request', function () {
    $wizard = Livewire::test(QuoteWizard::class, [
        'serviceSlug' => 'hurricane-wind-rated-doors',
        'quizAnswers' => ['age' => 'over-20', 'windows' => 'standard', 'condition' => 'some', 'bogus' => 'x'],
    ])->assertSet('step', QuoteWizard::DETAILS);

    foreach (['doorSize' => 'single', 'urgency' => 'flexible', 'name' => 'Sam Lee', 'phone' => '2525550199', 'email' => 'sam@example.com', 'street' => '1 Ocean Dr', 'town' => 'emerald-isle', 'zip' => '28594'] as $name => $value) {
        $wizard->set($name, $value);
    }
    $this->travel(5)->seconds();
    $wizard->call('submit')->assertHasNoErrors();

    expect(Lead::query()->sole()->answers)->toEqual([
        'door_size' => 'single',
        'hurricane' => ['age' => 'over-20', 'windows' => 'standard', 'condition' => 'some'],
    ]);
});

it('keeps only known attribution fields', function () {
    completedWizard(['attribution' => [
        'utm_source' => 'google',
        'gclid' => 'abc123',
        'landing_page' => '/services/garage-door-repair',
        'captured_at' => 1790000000000,
        'evil' => '<script>',
    ]])->call('submit')->assertHasNoErrors();

    expect(Lead::query()->sole()->attribution)->toEqual([
        'utm_source' => 'google',
        'gclid' => 'abc123',
        'landing_page' => '/services/garage-door-repair',
    ]);
});

it('shows the review even when the browser sends a contact method that doesn’t exist', function () {
    completedWizard(['preferredContact' => 'carrier-pigeon'])
        ->assertOk()
        ->call('submit')
        ->assertHasErrors('preferredContact')
        ->assertSet('step', QuoteWizard::CONTACT);
});

it('sends a step with a problem back into view on submit', function () {
    completedWizard(['zip' => '285'])
        ->call('submit')
        ->assertHasErrors('zip')
        ->assertSet('step', QuoteWizard::CONTACT);
});

it('turns away a form sent faster than a person could fill it in', function () {
    $wizard = completedWizard();
    // Earlier than the form was loaded, so no real time passing can make it look human.
    $this->travelTo(now()->subMinute());

    $wizard->call('submit')->assertForbidden();

    expect(Lead::query()->count())->toBe(0);
});

it('limits each visitor to five requests an hour', function () {
    foreach (range(1, QuoteWizard::HOURLY_LIMIT) as $attempt) {
        completedWizard()->call('submit')->assertHasNoErrors();
    }

    completedWizard()->call('submit')->assertHasErrors('submit');

    expect(Lead::query()->count())->toBe(QuoteWizard::HOURLY_LIMIT);
});

describe('with Turnstile keys set', function () {
    beforeEach(fn () => config(['services.turnstile.key' => '0x4AAA', 'services.turnstile.secret' => '0x4AAA-secret']));

    it('sends the request once Cloudflare passes the check', function () {
        Turnstile::fake();

        completedWizard(['turnstileToken' => Turnstile::dummy()])->call('submit')->assertHasNoErrors();

        expect(Lead::query()->count())->toBe(1);
    });

    it('holds the request and resets the widget when the check fails', function () {
        Turnstile::fake()->fail();

        completedWizard(['turnstileToken' => Turnstile::dummy()])
            ->call('submit')
            ->assertHasErrors('turnstileToken')
            ->assertSet('turnstileToken', '')
            ->assertDispatched('turnstile-reset');

        expect(Lead::query()->count())->toBe(0);
    });

    it('waits for the check to finish', function () {
        completedWizard()->call('submit')->assertHasErrors(['turnstileToken' => 'required']);
    });
});

it('reads the visitor’s address from Cloudflare only on Laravel Cloud', function () {
    $request = Request::create('/', server: ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_CF_CONNECTING_IP' => '203.0.113.9']);

    expect(QuoteWizard::clientIp($request))->toBe('10.0.0.1');

    $_SERVER['LARAVEL_CLOUD'] = '1';

    try {
        expect(QuoteWizard::clientIp($request))->toBe('203.0.113.9');
    } finally {
        unset($_SERVER['LARAVEL_CLOUD']);
    }
});
