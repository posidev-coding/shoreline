<?php

use App\Livewire\Portal\SignIn;
use App\Mail\PortalSignInCode;
use App\Models\Client;
use App\Models\OneTimePassword;
use App\Models\User;
use App\Support\PortalSignIn;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->portalUserOf(Client::factory()->create())->create(['email' => 'jane@example.com']);
});

/**
 * Asks for a code for the address, and returns the one emailed, if any.
 */
function requestCode(string $email): ?string
{
    Livewire::test(SignIn::class)->set('email', $email)->call('sendCode')->assertSet('codeSent', true);

    $code = null;
    Mail::assertQueued(PortalSignInCode::class, function (PortalSignInCode $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    return $code;
}

it('emails a portal user a code and a link, and keeps only a hash of the code', function () {
    $code = requestCode('Jane@Example.com ');

    Mail::assertQueued(PortalSignInCode::class, fn (PortalSignInCode $mail): bool => $mail->hasTo('jane@example.com'));
    expect($code)->toMatch('/^\d{6}$/')
        ->and(OneTimePassword::query()->sole()->password)->toBe(OneTimePassword::hashCode((string) $code))->not->toBe($code);

    $rendered = (new PortalSignInCode($this->user, (string) $code))->render();
    expect($rendered)->toContain($code)->toContain(e(route('portal.sign-in.confirm', ['email' => 'jane@example.com', 'code' => $code])));
});

it('says the same and sends nothing for an address without a portal', function (string $email) {
    Livewire::test(SignIn::class)
        ->set('email', $email)
        ->call('sendCode')
        ->assertHasNoErrors()
        ->assertSet('codeSent', true)
        ->assertSeeInOrder(['If', $email, 'has a portal']);

    Mail::assertNothingQueued();
})->with([
    'unknown' => fn (): string => 'nobody@example.com',
    'an admin' => fn (): string => User::factory()->owner()->create()->email,
    'removed from every client' => fn (): string => User::factory()->create(['password' => null])->email,
]);

it('signs in with the code, once, and keeps them signed in on this device', function () {
    $code = requestCode('jane@example.com');

    Livewire::test(SignIn::class)
        ->set('email', 'jane@example.com')
        ->set('codeSent', true)
        ->set('code', substr((string) $code, 0, 3).' '.substr((string) $code, 3))
        ->call('signIn')
        ->assertRedirect(route('portal.dashboard'));

    $this->assertAuthenticatedAs($this->user);
    expect($this->user->refresh()->last_signed_in_at)->not->toBeNull()
        ->and(OneTimePassword::query()->count())->toBe(0)
        ->and(PortalSignIn::attempt('jane@example.com', (string) $code, '203.0.113.1')->isOk())->toBeFalse();
});

it('refuses a wrong code and an expired one', function () {
    $code = requestCode('jane@example.com');
    $signIn = Livewire::test(SignIn::class)->set('email', 'jane@example.com')->set('codeSent', true);

    $signIn->set('code', $code === '000000' ? '111111' : '000000')->call('signIn')->assertHasErrors('code')->assertSee('That code didn’t work');

    $this->travel(PortalSignIn::CODE_MINUTES + 1)->minutes();
    $signIn->set('code', $code)->call('signIn')->assertSee('That code has expired');

    $this->assertGuest();
});

it('stops guessing after five wrong codes', function () {
    $code = (string) requestCode('jane@example.com');
    $wrong = $code === '000000' ? '111111' : '000000';
    $signIn = Livewire::test(SignIn::class)->set('email', 'jane@example.com')->set('codeSent', true);

    foreach (range(1, 5) as $attempt) {
        $signIn->set('code', $wrong)->call('signIn');
    }

    $signIn->set('code', $code)->call('signIn')->assertSee('Too many tries');
    $this->assertGuest();
});

it('limits how many codes an address can ask for', function () {
    foreach (range(1, PortalSignIn::CODES_PER_EMAIL) as $request) {
        Livewire::test(SignIn::class)->set('email', 'jane@example.com')->call('sendCode')->assertHasNoErrors();
    }

    Livewire::test(SignIn::class)->set('email', 'jane@example.com')->call('sendCode')->assertHasErrors('email')->assertSet('codeSent', false);
    Mail::assertQueued(PortalSignInCode::class, PortalSignIn::CODES_PER_EMAIL);
});

it('opens the emailed link on a page that only asks, so a mail scanner can’t use the code up', function () {
    $code = requestCode('jane@example.com');
    $link = route('portal.sign-in.confirm', ['email' => 'jane@example.com', 'code' => $code]);

    $this->get($link)->assertOk()->assertSee('jane@example.com')->assertSee('Sign in');

    $this->assertGuest();
    expect(OneTimePassword::query()->count())->toBe(1);
});

it('signs in from the link in another browser on another network', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7', 'HTTP_USER_AGENT' => 'Laptop Chrome']);
    $code = requestCode('jane@example.com');

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'Gmail app on iPhone'])
        ->post(route('portal.sign-in.store'), ['email' => 'jane@example.com', 'code' => $code])
        ->assertRedirect(route('portal.dashboard'));

    $this->assertAuthenticatedAs($this->user);
});

it('sends a used link back to the sign-in page', function () {
    $code = requestCode('jane@example.com');
    $this->post(route('portal.sign-in.store'), ['email' => 'jane@example.com', 'code' => $code]);
    auth()->logout();

    $this->post(route('portal.sign-in.store'), ['email' => 'jane@example.com', 'code' => $code])
        ->assertRedirect(route('portal.login', ['email' => 'jane@example.com']))
        ->assertSessionHas('status');

    $this->assertGuest();
});

it('sends a mangled link back to the sign-in page too', function () {
    $this->post(route('portal.sign-in.store'), ['email' => 'jane@example.com', 'code' => '12'])
        ->assertRedirect(route('portal.login', ['email' => 'jane@example.com']))
        ->assertSessionHas('status');
});

it('signs out', function () {
    $this->actingAs($this->user)
        ->post(route('portal.logout'))
        ->assertRedirect(route('portal.login'));

    $this->assertGuest();
});

it('sends someone already signed in to their projects', function () {
    $this->actingAs($this->user)->get(route('portal.login'))->assertRedirect(route('portal.dashboard'));
});
