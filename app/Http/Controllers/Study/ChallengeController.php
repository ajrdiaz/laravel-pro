<?php

namespace App\Http\Controllers\Study;

use App\Enums\CompletableType;
use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\Module;
use App\Services\ProgressReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChallengeController extends Controller
{
    /**
     * List the practice challenge of every module.
     */
    public function __invoke(Request $request, ProgressReport $progress): Response
    {
        $completed = $progress->completedIds($request->user(), CompletableType::Challenge)->flip();

        return Inertia::render('Challenges', [
            'challenges' => Module::orderBy('position')
                ->with('challenges')
                ->get()
                ->flatMap(fn (Module $module) => $module->challenges->map(fn (Challenge $challenge) => [
                    'id' => $challenge->id,
                    'body' => $challenge->body,
                    'completed' => $completed->has($challenge->id),
                    'module' => $module->only(['title', 'command']),
                ]))
                ->values(),
        ]);
    }
}
