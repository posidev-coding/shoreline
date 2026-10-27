<?php

namespace App\Models;

use Database\Factories\ProjectUpdateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An entry on a project's timeline. Shared ones appear in the client portal (M4).
 */
#[Fillable(['project_id', 'user_id', 'body', 'is_visible_to_client'])]
class ProjectUpdate extends Model
{
    /** @use HasFactory<ProjectUpdateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

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
            'is_visible_to_client' => 'boolean',
        ];
    }
}
