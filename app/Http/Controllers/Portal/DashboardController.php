<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ProjectStage;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The portal's home: the next visit, the jobs under way, and the finished ones.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $projects = Project::query()
            ->visibleInPortalTo($user)
            ->with(['client', 'property', 'nextVisit'])
            ->latest('stage_changed_at')
            ->get();

        $isFinished = fn (Project $project): bool => in_array($project->stage, ProjectStage::finished(), true);
        $active = $projects->reject($isFinished);
        $finished = $projects->filter($isFinished);

        return view('portal.dashboard', [
            'user' => $user,
            'active' => $active,
            'finished' => $finished->sortByDesc('completed_at')->take(10),
            'nextVisit' => Visit::query()
                ->whereIn('project_id', $active->modelKeys())
                ->where('ends_at', '>=', now())
                ->where('status', '!=', VisitStatus::Done)
                ->with('project.property')
                ->orderBy('starts_at')
                ->first(),
            'showClientNames' => $projects->pluck('client_id')->unique()->count() > 1,
        ]);
    }
}
