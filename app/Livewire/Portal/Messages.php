<?php

namespace App\Livewire\Portal;

use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\User;
use App\Settings\SiteSettings;
use App\Support\MessageThread;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;
use STS\FilamentImpersonate\Facades\Impersonation;

/**
 * A job's message thread in the portal. Its requests arrive at Livewire's own endpoint, not the
 * portal's routes, so every action checks again that the job is this person's to see, and that
 * their role lets them reply. Opening it marks the thread read, except for an admin viewing the
 * portal as someone, who can read but not post or change what's unread.
 */
class Messages extends Component
{
    #[Locked]
    public int $projectId;

    public string $body = '';

    public bool $sent = false;

    public function mount(Project $project): void
    {
        $this->projectId = $project->getKey();
    }

    public function send(): void
    {
        $user = $this->user();
        $project = $this->project($user);

        abort_if(Impersonation::isImpersonating(), 403, 'Messages can’t be sent while viewing as someone.');
        abort_unless(MessageThread::canPost($user, $project), 403);

        $this->sent = false;
        $this->body = trim($this->body);
        $this->validate(
            ['body' => ['required', 'string', 'max:'.MessageThread::MAX_LENGTH]],
            ['body.required' => 'Write a message first.', 'body.max' => 'That’s a long one. Keep it under '.number_format(MessageThread::MAX_LENGTH).' characters.'],
        );

        $key = 'portal-messages:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($key, MessageThread::CLIENT_MESSAGES_PER_HOUR)) {
            $this->addError('body', 'That’s a lot of messages. Try again in a little while, or give us a call.');

            return;
        }

        RateLimiter::hit($key, 3600);
        MessageThread::post($project, $user, $this->body);

        $this->reset('body');
        $this->sent = true;
    }

    public function render(): View
    {
        $user = $this->user();
        $project = $this->project($user);
        $messages = $project->messages()->with('author')->get();
        $isImpersonating = Impersonation::isImpersonating();
        $businessName = app(SiteSettings::class)->business_name;

        if ($messages->isNotEmpty() && ! $isImpersonating) {
            MessageThread::markRead($project, $user, $messages->last()->getKey());
        }

        return view('livewire.portal.messages', [
            'messages' => $messages,
            'user' => $user,
            'canPost' => ! $isImpersonating && MessageThread::canPost($user, $project),
            'isImpersonating' => $isImpersonating,
            'fromName' => fn (ProjectMessage $message): string => match (true) {
                ! $message->is_from_client => $businessName,
                $message->user_id === $user->getKey() => 'You',
                default => $message->author->name ?? 'Someone no longer in the portal',
            },
        ]);
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->canUsePortal(), 403);

        return $user;
    }

    /**
     * Someone else's job is a 404, the same as on the portal's pages.
     */
    private function project(User $user): Project
    {
        return Project::query()->visibleInPortalTo($user)->with('client')->findOrFail($this->projectId);
    }
}
