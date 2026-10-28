<?php

namespace App\Filament\Resources\Projects\Pages\Concerns;

use App\Models\Project;
use App\Models\User;
use App\Support\MessageThread;

/**
 * Marks the client's messages read while the job's Messages tab is open. It runs as the page
 * starts rendering, before the tabs' badges are counted, so the open tab doesn't show them
 * still unread.
 */
trait MarksMessagesRead
{
    public function renderingMarksMessagesRead(): void
    {
        $user = auth()->user();
        $project = $this->getRecord();

        if ($this->activeRelationManager !== 'messages' || ! $user instanceof User || ! $project instanceof Project) {
            return;
        }

        $latest = $project->messages()->max('id');

        if ($latest !== null) {
            MessageThread::markRead($project, $user, (int) $latest);
        }
    }
}
