<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * A job's thread between the business and the client's portal users. Posting marks the message
 * read for its author and emails the other side a few minutes later, once, and only if they
 * haven't read it in the meantime.
 */
final class MessageThread
{
    /**
     * Long enough that a burst of messages sends one email, and someone reading along in the
     * portal gets none.
     */
    public const int EMAIL_DELAY_MINUTES = 5;

    public const int MAX_LENGTH = 5000;

    /**
     * Across all of a portal user's jobs.
     */
    public const int CLIENT_MESSAGES_PER_HOUR = 30;

    /**
     * An admin, or a portal user who sees the job and whose role there lets them reply.
     */
    public static function canPost(User $user, Project $project): bool
    {
        if ($user->role->canAccessAdmin()) {
            return true;
        }

        return Project::query()->visibleInPortalTo($user)->whereKey($project->getKey())->exists()
            && ($user->portalRoleFor($project->client)?->canSendMessages() ?? false);
    }

    public static function post(Project $project, User $author, string $body): ProjectMessage
    {
        $fromClient = ! $author->role->canAccessAdmin();

        $message = DB::transaction(function () use ($project, $author, $body, $fromClient): ProjectMessage {
            $message = $project->messages()->create([
                'user_id' => $author->getKey(),
                'is_from_client' => $fromClient,
                'body' => trim($body),
            ]);

            self::markRead($project, $author, $message->getKey());

            return $message;
        });

        $recipients = $fromClient ? User::alertRecipients() : ClientNotices::messageRecipients($project);

        Notification::send(
            $recipients->reject(fn (User $user): bool => $user->is($author)),
            (new NewMessage($message))->delay(now()->addMinutes(self::EMAIL_DELAY_MINUTES)),
        );

        return $message;
    }

    /**
     * Records that the person has read the thread up to and including this message. It never
     * moves back, so a stale page can't mark newer messages unread.
     */
    public static function markRead(Project $project, User $user, int $lastMessageId): void
    {
        DB::statement(
            'insert into project_message_reads (project_id, user_id, last_read_message_id, read_at) values (?, ?, ?, ?)
                on conflict (project_id, user_id) do update set
                    last_read_message_id = greatest(project_message_reads.last_read_message_id, excluded.last_read_message_id),
                    read_at = excluded.read_at',
            [$project->getKey(), $user->getKey(), $lastMessageId, now()],
        );
    }

    /**
     * Messages from the other side that the person hasn't read: clients' messages for an admin,
     * the business's for a portal user.
     *
     * @return Builder<ProjectMessage>
     */
    public static function unread(User $user): Builder
    {
        return ProjectMessage::query()
            ->where('is_from_client', $user->role->canAccessAdmin())
            ->whereNotExists(fn (QueryBuilder $read) => $read
                ->from('project_message_reads')
                ->whereColumn('project_message_reads.project_id', 'project_messages.project_id')
                ->where('project_message_reads.user_id', $user->getKey())
                ->whereColumn('project_message_reads.last_read_message_id', '>=', 'project_messages.id'));
    }
}
