<?php

namespace App\Http\Controllers\Study;

use App\Enums\CompletableType;
use App\Http\Controllers\Controller;
use App\Models\InterviewQuestion;
use App\Models\Module;
use App\Services\ProgressReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InterviewController extends Controller
{
    /**
     * List the interview question of every module.
     */
    public function __invoke(Request $request, ProgressReport $progress): Response
    {
        $completed = $progress->completedIds($request->user(), CompletableType::InterviewQuestion)->flip();

        return Inertia::render('Interview', [
            'questions' => Module::orderBy('position')
                ->with('interviewQuestions')
                ->get()
                ->flatMap(fn (Module $module) => $module->interviewQuestions->map(fn (InterviewQuestion $question) => [
                    'id' => $question->id,
                    'question' => $question->question,
                    'points' => $question->points,
                    'completed' => $completed->has($question->id),
                    'module' => $module->title,
                ]))
                ->values(),
        ]);
    }
}
