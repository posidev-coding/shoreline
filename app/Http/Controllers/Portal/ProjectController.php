<?php

namespace App\Http\Controllers\Portal;

use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * One job as its client sees it. A job that isn't theirs is a 404, the same as one that doesn't
 * exist, so its address can't be confirmed by trying.
 */
class ProjectController extends Controller
{
    public function __invoke(Request $request, string $ulid): View
    {
        /** @var User $user */
        $user = $request->user();

        $project = Project::query()
            ->visibleInPortalTo($user)
            ->where('ulid', strtolower($ulid))
            ->with(['client', 'property', 'visits'])
            ->firstOrFail();

        Gate::authorize('view', $project);

        $isOver = fn (Visit $visit): bool => $visit->status === VisitStatus::Done || $visit->ends_at->isPast();
        $upcoming = $project->visits->reject($isOver);
        $past = $project->visits->filter($isOver);

        return view('portal.project', [
            'project' => $project,
            'upcoming' => $upcoming,
            'past' => $past->sortByDesc('starts_at'),
        ]);
    }
}
