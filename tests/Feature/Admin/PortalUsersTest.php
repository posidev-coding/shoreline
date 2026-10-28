<?php

use App\Enums\ClientUserRole;
use App\Enums\UserRole;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\PortalUsersRelationManager;
use App\Mail\PortalInvitation;
use App\Models\Client;
use App\Models\User;
use App\Support\PortalAccess;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use STS\FilamentImpersonate\Facades\Impersonation;

beforeEach(function () {
    Mail::fake();
    $this->actingAs($this->owner = User::factory()->owner()->withMfa()->create());
    $this->client = Client::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com']);
});

function portalTab(Client $client): mixed
{
    return Livewire::test(PortalUsersRelationManager::class, ['ownerRecord' => $client, 'pageClass' => ViewClient::class]);
}

it('invites the client, making a passwordless portal user and emailing a link to sign in', function () {
    portalTab($this->client)
        ->mountAction(TestAction::make('invite')->table())
        ->assertActionDataSet(['name' => 'Jane Smith', 'email' => 'jane@example.com', 'role' => ClientUserRole::Primary])
        ->callMountedAction()
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'jane@example.com')->sole();
    expect($user)
        ->role->toBe(UserRole::Client)
        ->password->toBeNull()
        ->and($this->client->portalUsers()->sole()->access->client_role)->toBe(ClientUserRole::Primary);

    Mail::assertQueued(PortalInvitation::class, fn (PortalInvitation $mail): bool => $mail->hasTo('jane@example.com')
        && str_contains($mail->render(), e(route('portal.login', ['email' => 'jane@example.com']))));
    expect(Activity::query()->where('subject_id', $this->client->getKey())->where('event', 'portal')->value('description'))
        ->toBe('Invited jane@example.com to the portal as Primary contact');
});

it('adds someone already in another client’s portal without renaming them', function () {
    $manager = User::factory()->portalUserOf(Client::factory()->create())->create(['name' => 'Pat Manager', 'email' => 'pat@example.com']);

    portalTab($this->client)
        ->callAction(TestAction::make('invite')->table(), data: ['name' => 'Patricia', 'email' => 'PAT@example.com', 'role' => ClientUserRole::Manager->value])
        ->assertHasNoErrors();

    expect($manager->refresh())
        ->name->toBe('Pat Manager')
        ->and($manager->clients()->count())->toBe(2);
});

it('won’t invite an admin, or someone who already has access', function () {
    $admin = User::factory()->owner()->create();
    User::factory()->portalUserOf($this->client)->create(['email' => 'already@example.com']);

    portalTab($this->client)
        ->callAction(TestAction::make('invite')->table(), data: ['name' => 'Admin', 'email' => $admin->email, 'role' => ClientUserRole::Primary->value])
        ->assertHasActionErrors(['email']);
    portalTab($this->client)
        ->callAction(TestAction::make('invite')->table(), data: ['name' => 'Again', 'email' => 'Already@example.com', 'role' => ClientUserRole::Primary->value])
        ->assertHasActionErrors(['email']);

    expect($admin->refresh()->clients()->exists())->toBeFalse()
        ->and(fn () => PortalAccess::invite($this->client, 'Admin', $admin->email, ClientUserRole::Primary))->toThrow(InvalidArgumentException::class);
    Mail::assertNothingQueued();
});

it('resends an invitation and changes a role', function () {
    $user = User::factory()->portalUserOf($this->client, ClientUserRole::Viewer)->create();

    portalTab($this->client)
        ->callAction(TestAction::make('resend')->table($user))
        ->callAction(TestAction::make('changeRole')->table($user), data: ['role' => ClientUserRole::Billing->value])
        ->assertHasNoErrors();

    Mail::assertQueued(PortalInvitation::class, 1);
    expect($this->client->portalUsers()->sole()->access)
        ->client_role->toBe(ClientUserRole::Billing)
        ->invited_at->not->toBeNull();
});

it('removes access, deleting a person who has no other client', function () {
    $user = User::factory()->portalUserOf($this->client)->create();

    portalTab($this->client)->callAction(TestAction::make('remove')->table($user));

    expect(User::query()->find($user->getKey()))->toBeNull();
});

it('views the portal as one of its users, and logs it', function () {
    $user = User::factory()->portalUserOf($this->client)->create(['email' => 'jane@example.com']);

    portalTab($this->client)
        ->callAction(TestAction::make('impersonate')->table($user))
        ->assertRedirect(route('portal.dashboard'));

    expect(Impersonation::isImpersonating())->toBeTrue()
        ->and(auth()->id())->toBe($user->getKey())
        ->and(Activity::query()->where('event', 'impersonated')->sole())
        ->causer_id->toBe($this->owner->getKey())
        ->description->toBe('Started viewing the portal as jane@example.com');

    $this->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Impersonating')
        ->assertDontSee('Sign out');
});

it('can’t view the portal as another admin', function () {
    expect(User::factory()->owner()->create()->canBeImpersonated())->toBeFalse()
        ->and(User::factory()->portalUserOf($this->client)->create()->canBeImpersonated())->toBeTrue();
});
