<?php

namespace App\Http\Controllers\Study;

use App\Enums\CompletableType;
use App\Http\Controllers\Controller;
use App\Models\Concept;
use App\Services\ModuleProgress;
use App\Services\ProgressReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoadmapController extends Controller
{
    /**
     * Show every module with its concepts and how far the user has got.
     */
    public function __invoke(Request $request, ProgressReport $progress): Response
    {
        $user = $request->user();

        $modules = $progress->modules($user);
        $completed = $progress->completedIds($user, CompletableType::Concept)->flip();
        $concepts = Concept::orderBy('position')->get()->groupBy('module_id');

        return Inertia::render('Roadmap', [
            'stats' => $progress->stats($user, $modules),
            'modules' => $modules->map(fn (ModuleProgress $module) => [
                ...$module->toArray(),
                'concepts' => $concepts->get($module->id, collect())
                    ->map(fn (Concept $concept) => [
                        'id' => $concept->id,
                        'body' => $concept->body,
                        'completed' => $completed->has($concept->id),
                    ])
                    ->values(),
            ]),
        ]);
    }
}
