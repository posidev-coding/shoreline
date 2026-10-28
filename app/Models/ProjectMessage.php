<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Database\Factories\ProjectMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a job's thread, from the business or from one of the client's portal users.
 * Post through App\Support\MessageThread, which marks it read for its author and emails the
 * other side.
 *
 * @property-read Project $project
 * @property-read User|null $author
 */
#[Fillable(['project_id', 'user_id', 'is_from_client', 'body'])]
class ProjectMessage extends Model
{
    /** @use HasFactory<ProjectMessageFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'is_from_client' => 'boolean',
        ];
    }
}
