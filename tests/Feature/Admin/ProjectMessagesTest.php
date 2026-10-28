<?php

use App\Enums\ClientUserRole;
use App\Enums\ProjectStage;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\MessagesRelationManager;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\Property;
use App\Models\User;
use App\Notifications\NewMessage;
use App\Support\MessageThread;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->actingAs($this->owner = User::factory()->owner()->withMfa()->create());
    $this->client = Client::factory()->create(['display_name' => 'Jane Homeowner']);
    $this->project = Project::factory()->for(Property::factory()->for($this->client))->stage(ProjectStage::Scheduled)->create();
});

function messagesTab(Project $project): mixed
{
    return Livewire::test(MessagesRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class]);
}

it('sends a message to the client’s portal and says who will be emailed', function () {
    $jane = User::factory()->portalUserOf($this->client)->create(['name' => 'Jane Smith']);
    User::factory()->portalUserOf($this->client, ClientUserRole::Viewer)->create(['name' => 'Vic Viewer']);

    messagesTab($this->project)
        ->assertActionExists(TestAction::make('send')->table(), fn (Action $action): bool => str_contains((string) $action->getModalDescription(), 'Jane Smith will be emailed in '.MessageThread::EMAIL_DELAY_MINUTES.' minutes')
            && ! str_contains((string) $action->getModalDescription(), 'Vic Viewer'))
        ->callAction(TestAction::make('send')->table(), data: ['body' => 'The door ships Thursday.'])
        ->assertHasNoErrors();

    expect(ProjectMessage::query()->sole())->is_from_client->toBeFalse()->body->toBe('The door ships Thursday.');
    Notification::assertSentTo($jane, NewMessage::class);
});

it('warns when nobody at the client can reply in the portal yet', function () {
    messagesTab($this->project)
        ->assertActionExists(TestAction::make('send')->table(), fn (Action $action): bool => str_starts_with((string) $action->getModalDescription(), 'Nobody at Jane Homeowner can reply in the portal yet'));
});

it('counts the client’s unread messages on the tab, and opening it reads them', function () {
    $jane = User::factory()->portalUserOf($this->client)->create();
    MessageThread::post($this->project, $jane, 'Is Thursday still on?');
    MessageThread::post($this->project, $jane, 'Also, the gate sticks.');

    expect(MessagesRelationManager::getBadge($this->project, ViewProject::class))->toBe('2');

    // Another tab leaves them unread; opening Messages reads them before its badge is drawn.
    $badgeOnMessagesTab = '/Messages\s*<\/span>\s*<span\s+class="fi-badge/';
    $page = Livewire::test(ViewProject::class, ['record' => $this->project->getRouteKey()]);
    expect(preg_match($badgeOnMessagesTab, $page->html()))->toBe(1);

    $page->set('activeRelationManager', 'messages');
    expect(preg_match($badgeOnMessagesTab, $page->html()))->toBe(0);
    messagesTab($this->project)->assertSee(['Is Thursday still on?', 'Also, the gate sticks.']);

    expect(MessagesRelationManager::getBadge($this->project, ViewProject::class))->toBeNull()
        ->and(MessageThread::unread(User::factory()->owner()->create())->count())->toBe(2);
});

it('has no thread on a job the client can’t see', function () {
    $this->project->update(['stage' => ProjectStage::Lost, 'lost_reason' => 'Price']);

    messagesTab($this->project)->assertActionHidden(TestAction::make('send')->table());
});
