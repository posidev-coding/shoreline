<?php

namespace App\Models\Concerns;

use App\Models\Project;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record that belongs to a project, trashed or not, so it always shows its job. Lists that
 * should leave out deleted jobs say so with withoutTrashedProjects(): whereHas('project') alone
 * doesn't, since the relation includes them.
 *
 * @phpstan-require-extends Model
 */
trait BelongsToProject
{
    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function withoutTrashedProjects(Builder $query): void
    {
        $query->whereHas('project', fn (Builder $project) => $project->withoutTrashed());
    }
}
