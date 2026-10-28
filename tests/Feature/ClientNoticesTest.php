<?php

use App\Enums\ClientUserRole;
use App\Enums\ProjectStage;
use App\Enums\VisitNoticeKind;
use App\Enums\VisitStatus;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\VisitsRelationManager;
use App\Jobs\SendVisitNotice;
use App\Models\Client;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\JobFinished;
use App\Notifications\NewMessage;
use App\Notifications\VisitNotice;
use App\Support\ClientNotices;
use App\Support\MessageThread;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create(['name' => 'Owen Owner']);
    $this->client = Client::factory()->create(['display_name' => 'Demo Rentals']);
    $this->property = Property::factory()->for($this->client)->create(['street' => '8 Ocean Dr']);
    $this->project = Project::factory()->for($this->property)->stage(ProjectStage::Scheduled)->create(['title' => 'Opener install']);

    $this->primary = User::factory()->portalUserOf($this->client)->create(['name' => 'Pat Primary']);
    $this->manager = User::factory()->portalUserOf($this->client, ClientUserRole::Manager)->create();
    $this->billing = User::factory()->portalUserOf($this->client, ClientUserRole::Billing)->create();
    $this->viewer = User::factory()->portalUserOf($this->client, ClientUserRole::Viewer)->create();
});

describe('messages', function () {
    it('emails the people who can reply, a few minutes later', function () {
        Notification::fake();

        MessageThread::post($this->project, $this->owner, 'The opener arrives Friday.');

        Notification::assertSentTo([$this->primary, $this->manager, $this->billing], NewMessage::class);
        Notification::assertNotSentTo([$this->viewer, $this->owner], NewMessage::class);
    });

    it('sends one email for a burst, listing every unread message, and none once they’ve read it', function () {
        $first = MessageThread::post($this->project, $this->owner, 'The opener arrives Friday.');
        $second = MessageThread::post($this->project, $this->owner, "Also: <b>bring</b> the remote.\nThanks!");

        expect((new NewMessage($first))->shouldSend($this->primary, 'mail'))->toBeFalse()
            ->and((new NewMessage($second))->shouldSend($this->primary, 'mail'))->toBeTrue();

        $html = (string) (new NewMessage($second))->toMail($this->primary)->render();
        expect($html)
            ->toContain('sent you 2 messages about Opener install')
            ->toContain('The opener arrives Friday.')
            ->toContain('Also: &lt;b&gt;bring&lt;/b&gt; the remote.<br>')
            ->not->toContain('Owen Owner')
            ->toContain(e(route('portal.projects.show', $this->project->ulid).'#messages'));

        MessageThread::markRead($this->project, $this->primary, $second->getKey());
        expect((new NewMessage($second))->shouldSend($this->primary, 'mail'))->toBeFalse()
            ->and((new NewMessage($second))->shouldSend($this->manager, 'mail'))->toBeTrue();
    });

    it('tells the owner by email and in the bell, with replies going to the client', function () {
        $message = MessageThread::post($this->project, $this->primary, 'Is Friday still on?');
        $notification = new NewMessage($message);

        expect($notification->via($this->owner))->toBe(['mail', 'database'])
            ->and($notification->shouldSend($this->owner, 'mail'))->toBeTrue();

        $mail = $notification->toMail($this->owner);
        expect($mail->subject)->toBe('Message from Pat Primary: Opener install')
            ->and($mail->replyTo)->toBe([[$this->primary->email, 'Pat Primary']])
            ->and((string) $mail->render())->toContain('Is Friday still on?')->toContain('relation=messages')
            ->and($notification->toDatabase($this->owner)['title'])->toBe('Message from Pat Primary');
    });

    it('stops emailing someone who lost access to the job', function () {
        $message = MessageThread::post($this->project, $this->owner, 'The opener arrives Friday.');
        $this->project->update(['stage' => ProjectStage::Cancelled]);

        expect((new NewMessage($message))->shouldSend($this->primary, 'mail'))->toBeFalse();
    });
});

describe('visits', function () {
    it('emails the people following the work once a visit is confirmed, and records it', function () {
        Notification::fake();
        $visit = Visit::factory()->for($this->project)->create();

        Notification::assertNothingSent();

        $visit->update(['status' => VisitStatus::Confirmed]);

        Notification::assertSentTo([$this->primary, $this->manager, $this->viewer], VisitNotice::class, fn (VisitNotice $notice): bool => $notice->kind === VisitNoticeKind::Confirmed
            && $notice->window === $visit->window());
        Notification::assertNotSentTo($this->billing, VisitNotice::class);
        expect($visit->refresh()->client_notified_at)->not->toBeNull()
            ->and($visit->notified_starts_at->equalTo($visit->starts_at))->toBeTrue();
    });

    it('waits a couple of minutes before checking', function () {
        Queue::fake();

        Visit::factory()->confirmed()->for($this->project)->create();

        Queue::assertPushed(SendVisitNotice::class, fn (SendVisitNotice $job): bool => $job->delay?->greaterThan(now()->addMinutes(ClientNotices::DELAY_MINUTES - 1)) === true);
    });

    it('tells them about a move with the old time, and nothing when the time didn’t change', function () {
        $visit = Visit::factory()->confirmed()->for($this->project)->create();
        $told = $visit->refresh()->window();
        Notification::fake();

        $visit->update(['notes' => 'Bring the ladder']);
        Notification::assertNothingSent();

        $visit->update(['starts_at' => $visit->starts_at->addDay(), 'ends_at' => $visit->ends_at->addDay()]);

        Notification::assertSentTo($this->primary, VisitNotice::class, fn (VisitNotice $notice): bool => $notice->kind === VisitNoticeKind::Moved
            && $notice->previousWindow === $told
            && $notice->window === $visit->refresh()->window());
    });

    it('sends nothing when a move is undone before the email goes', function () {
        $visit = Visit::factory()->confirmed()->for($this->project)->create();
        Notification::fake();
        $original = [$visit->starts_at, $visit->ends_at];

        Visit::withoutEvents(fn () => $visit->update(['starts_at' => $visit->starts_at->addDay(), 'ends_at' => $visit->ends_at->addDay()]));
        $visit->update(['starts_at' => $original[0], 'ends_at' => $original[1]]);
        (new SendVisitNotice($visit->refresh()))->handle();

        Notification::assertNothingSent();
    });

    it('calls off a visit they were told about when it’s deleted, and says nothing about one they weren’t', function () {
        $told = Visit::factory()->confirmed()->for($this->project)->create();
        $pencilled = Visit::factory()->for($this->project)->create();
        $window = $told->refresh()->window();
        Notification::fake();

        $pencilled->delete();
        Notification::assertNothingSent();

        $told->delete();
        Notification::assertSentTo($this->primary, VisitNotice::class, fn (VisitNotice $notice): bool => $notice->kind === VisitNoticeKind::CalledOff && $notice->window === $window);
    });

    it('emails nobody, and records nothing, for a client without portal access', function () {
        Notification::fake();
        $project = Project::factory()->stage(ProjectStage::Scheduled)->create();

        $visit = Visit::factory()->confirmed()->for($project)->create();

        Notification::assertNothingSent();
        expect($visit->refresh()->client_notified_at)->toBeNull();
    });

    it('renders each kind of visit email', function (VisitNoticeKind $kind, string $expected) {
        $html = (string) (new VisitNotice($this->project, $kind, 'Install', 'Thu, Oct 29 · 9–11 AM', 'Wed, Oct 28 · 9–11 AM'))
            ->toMail($this->primary)->render();

        expect($html)->toContain('Hi Pat,')->toContain($expected)->toContain('Thu, Oct 29 · 9–11 AM')->toContain('8 Ocean Dr');
    })->with([
        [VisitNoticeKind::Confirmed, 'Your install visit is confirmed.'],
        [VisitNoticeKind::Moved, 'It was Wed, Oct 28 · 9–11 AM.'],
        [VisitNoticeKind::CalledOff, 'We’ve called off the install visit'],
    ]);
});

describe('finished jobs', function () {
    it('emails the people following the work when the job is finished', function () {
        Notification::fake();

        $this->project->update(['stage' => ProjectStage::Completed]);

        Notification::assertSentTo([$this->primary, $this->manager, $this->viewer], JobFinished::class, fn (JobFinished $notice): bool => $notice->delay !== null);
        Notification::assertNotSentTo($this->billing, JobFinished::class);

        $html = (string) (new JobFinished($this->project, $this->project->completed_at))->toMail($this->primary)->render();
        expect($html)->toContain('We’ve finished Opener install at 8 Ocean Dr.');
    });

    it('sends nothing if the job was reopened before the email went', function () {
        $this->project->update(['stage' => ProjectStage::Completed]);
        $notice = new JobFinished($this->project->refresh(), $this->project->completed_at);

        expect($notice->shouldSend($this->primary, 'mail'))->toBeTrue();

        $this->project->update(['stage' => ProjectStage::InProgress]);
        expect((new JobFinished($this->project->refresh(), $notice->completedAt))->shouldSend($this->primary, 'mail'))->toBeFalse();
    });
});

it('calls off a visit deleted from the job’s Visits tab', function () {
    $this->actingAs($this->owner);
    $visit = Visit::factory()->confirmed()->for($this->project)->create();
    Visit::factory()->for($this->project)->create();
    Notification::fake();

    Livewire::test(VisitsRelationManager::class, ['ownerRecord' => $this->project, 'pageClass' => ViewProject::class])
        ->callAction(TestAction::make('delete')->table($visit));

    Notification::assertSentTo($this->primary, VisitNotice::class, fn (VisitNotice $notice): bool => $notice->kind === VisitNoticeKind::CalledOff);
});
