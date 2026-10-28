<?php

use App\Enums\ClientUserRole;
use App\Enums\ProjectStage;
use App\Livewire\Portal\Messages;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\Property;
use App\Models\User;
use App\Notifications\NewMessage;
use App\Support\MessageThread;
use App\Support\PortalAccess;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use STS\FilamentImpersonate\Facades\Impersonation;

beforeEach(function () {
    Notification::fake();
    $this->owner = User::factory()->owner()->create(['name' => 'Owen Owner']);
    $this->client = Client::factory()->create(['display_name' => 'Jane Homeowner']);
    $this->user = User::factory()->portalUserOf($this->client)->create(['name' => 'Jane Smith']);
    $this->project = Project::factory()->for(Property::factory()->for($this->client))->stage(ProjectStage::Scheduled)->create(['title' => 'New door']);
});

it('sends a message to the business, which hears about it a few minutes later', function () {
    $this->actingAs($this->user);

    Livewire::test(Messages::class, ['project' => $this->project])
        ->set('body', "  When are you coming?\nThe gate code is on the post.  ")
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('body', '')
        ->assertSee('Sent. We’ll get back to you soon.')
        ->assertSee('When are you coming?');

    $message = ProjectMessage::query()->sole();
    expect($message)
        ->is_from_client->toBeTrue()
        ->user_id->toBe($this->user->getKey())
        ->body->toBe("When are you coming?\nThe gate code is on the post.")
        ->and(MessageThread::unread($this->owner)->count())->toBe(1);

    Notification::assertSentTo($this->owner, NewMessage::class, fn (NewMessage $notification): bool => $notification->message->is($message)
        && $notification->delay?->greaterThan(now()->addMinutes(MessageThread::EMAIL_DELAY_MINUTES - 1)));
    Notification::assertNotSentTo($this->user, NewMessage::class);
});

it('marks the business’s messages read when the thread is opened, and the dashboard says so', function () {
    MessageThread::post($this->project, $this->owner, 'The door ships Thursday.');
    $this->actingAs($this->user);

    $this->get(route('portal.dashboard'))->assertSee('New message');
    $this->get(route('portal.projects.show', $this->project->ulid))->assertSee(['The door ships Thursday.', 'Shoreline Doors'])->assertDontSee('Owen Owner');
    $this->get(route('portal.dashboard'))->assertDontSee('New message');
});

it('lets a viewer read the thread but not reply', function () {
    $viewer = User::factory()->portalUserOf($this->client, ClientUserRole::Viewer)->create();
    MessageThread::post($this->project, $this->owner, 'The door ships Thursday.');
    $this->actingAs($viewer);

    Livewire::test(Messages::class, ['project' => $this->project])
        ->assertSee(['The door ships Thursday.', 'your access doesn’t include replying'])
        ->assertDontSee('Send a message')
        ->set('body', 'Can I reply?')
        ->call('send')
        ->assertForbidden();

    expect(ProjectMessage::query()->count())->toBe(1);
});

it('won’t open someone else’s job, or a job that never happened', function (string $which) {
    $project = match ($which) {
        'another client’s' => Project::factory()->create(),
        'lost' => tap($this->project)->update(['stage' => ProjectStage::Lost, 'lost_reason' => 'Price']),
    };
    $this->actingAs($this->user);

    Livewire::test(Messages::class, ['project' => $project])->assertNotFound();
})->with(['another client’s', 'lost']);

it('stops someone whose access was removed from posting from a page left open', function () {
    $this->actingAs($this->user);
    $thread = Livewire::test(Messages::class, ['project' => $this->project])->set('body', 'Still here?');

    // Still in another client's portal, so still signed in, but not to this job.
    $other = Client::factory()->create();
    $other->portalUsers()->attach($this->user->getKey(), ['client_role' => ClientUserRole::Primary->value]);
    PortalAccess::remove($this->client, $this->user);

    $thread->call('send')->assertNotFound();
    expect(ProjectMessage::query()->count())->toBe(0);
});

it('lets an admin viewing as the client read, without posting or marking anything read', function () {
    MessageThread::post($this->project, $this->owner, 'The door ships Thursday.');
    $this->actingAs($this->owner);
    Impersonation::enter($this->owner, $this->user);

    Livewire::test(Messages::class, ['project' => $this->project])
        ->assertSee(['The door ships Thursday.', 'You’re viewing the portal as Jane Smith'])
        ->set('body', 'Typed as the client')
        ->call('send')
        ->assertForbidden();

    expect(ProjectMessage::query()->count())->toBe(1)
        ->and(MessageThread::unread($this->user)->count())->toBe(1);
});

it('limits how many messages a portal user can send in an hour', function () {
    $this->actingAs($this->user);
    $thread = Livewire::test(Messages::class, ['project' => $this->project]);

    foreach (range(1, MessageThread::CLIENT_MESSAGES_PER_HOUR) as $count) {
        $thread->set('body', "Message {$count}")->call('send')->assertHasNoErrors();
    }

    $thread->set('body', 'One too many')->call('send')->assertHasErrors('body');
    expect(ProjectMessage::query()->count())->toBe(MessageThread::CLIENT_MESSAGES_PER_HOUR);
});

it('refuses an empty message', function () {
    $this->actingAs($this->user);

    Livewire::test(Messages::class, ['project' => $this->project])
        ->set('body', "   \n ")
        ->call('send')
        ->assertHasErrors(['body' => 'required']);
});
