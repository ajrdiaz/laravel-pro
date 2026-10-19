<?php

namespace App\Http\Controllers\Study;

use App\Enums\CompletableType;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Services\ProgressReport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * List the portfolio projects, each with how many of its milestones the user has done.
     */
    public function index(Request $request, ProgressReport $progress): Response
    {
        $done = $progress->completedIds($request->user(), CompletableType::ProjectMilestone)->flip();

        return Inertia::render('Projects', [
            'projects' => Project::orderBy('position')
                ->with(['milestones:id,project_id', 'stack:id,slug,name'])
                ->withCount('modules')
                ->get()
                ->map(fn (Project $project) => [
                    'slug' => $project->slug,
                    'title' => $project->title,
                    'level' => ['value' => $project->level->value, 'label' => $project->level->label()],
                    'hours' => $project->hours,
                    'summary' => $project->summary,
                    'stack' => $project->stack?->only(['slug', 'name']),
                    'modules' => $project->modules_count,
                    'progress' => $this->progressOf($project, $done),
                ]),
        ]);
    }

    /**
     * Show one project: what to build, the milestones to tick off, and the roadmap modules
     * it puts into practice with the user's progress in each.
     */
    public function show(Request $request, Project $project, ProgressReport $progress): Response
    {
        $user = $request->user();

        $project->load(['milestones', 'modules', 'stack:id,slug,name']);

        $done = $progress->completedIds($user, CompletableType::ProjectMilestone)->flip();
        $moduleProgress = $progress->modules($user)->keyBy('id');

        return Inertia::render('ProjectShow', [
            'project' => [
                'slug' => $project->slug,
                'title' => $project->title,
                'level' => ['value' => $project->level->value, 'label' => $project->level->label()],
                'hours' => $project->hours,
                'summary' => $project->summary,
                'deliverables' => $project->deliverables,
                'stretch' => $project->stretch,
                'stack' => $project->stack?->only(['slug', 'name']),
                'progress' => $this->progressOf($project, $done),
            ],
            'milestones' => $project->milestones->map(fn (ProjectMilestone $milestone) => [
                'id' => $milestone->id,
                'title' => $milestone->title,
                'detail' => $milestone->detail,
                'completed' => $done->has($milestone->id),
            ]),
            'modules' => $project->modules->map(fn (Module $module) => [
                'slug' => $module->slug,
                'title' => $module->title,
                'command' => $module->command,
                'progress' => $moduleProgress[$module->id]->toArray()['progress'],
            ]),
        ]);
    }

    /**
     * @param  Collection<int, int>  $done  Ids of the milestones the user ticked, as keys.
     * @return array{done: int, total: int, pct: int}
     */
    private function progressOf(Project $project, Collection $done): array
    {
        $total = $project->milestones->count();
        $ticked = $project->milestones->filter(fn (ProjectMilestone $milestone) => $done->has($milestone->id))->count();

        return [
            'done' => $ticked,
            'total' => $total,
            'pct' => $total > 0 ? (int) round($ticked * 100 / $total) : 0,
        ];
    }
}
