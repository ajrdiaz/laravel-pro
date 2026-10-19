<?php

namespace App\Http\Controllers\Study;

use App\Actions\Study\AnswerQuizQuestion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Study\AnswerQuizQuestionRequest;
use App\Models\Module;
use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuizController extends Controller
{
    /** Questions per round when the quiz isn't limited to one module. */
    private const int ROUND_SIZE = 6;

    /**
     * Show a round of questions: every question of one module (`?module=<slug>`),
     * or a random sample across all of them.
     */
    public function index(Request $request): Response
    {
        $modules = Module::orderBy('position')->get(['id', 'slug', 'title']);
        $module = $modules->firstWhere('slug', $request->query('module'));

        return Inertia::render('Quiz', [
            'modules' => $modules->map->only(['slug', 'title']),
            'filters' => ['module' => $module->slug ?? 'all'],
            'questions' => QuizQuestion::query()
                ->with('module:id,title')
                ->when($module, fn (Builder $query) => $query->where('module_id', $module?->id))
                ->inRandomOrder()
                ->unless($module, fn (Builder $query) => $query->limit(self::ROUND_SIZE))
                ->get()
                ->map(fn (QuizQuestion $question) => [
                    'id' => $question->id,
                    'question' => $question->question,
                    'options' => $question->options,
                    'correct_index' => $question->correct_index,
                    'explanation' => $question->explanation,
                    'module' => $question->module->title,
                ]),
        ]);
    }

    /**
     * Record the option the user picked.
     */
    public function answer(AnswerQuizQuestionRequest $request, QuizQuestion $question, AnswerQuizQuestion $action): RedirectResponse
    {
        $action->handle($request->user(), $question, $request->integer('selected'));

        return back();
    }
}
