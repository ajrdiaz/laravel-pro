<?php

namespace App\Http\Controllers\Study;

use App\Http\Controllers\Controller;
use App\Http\Requests\Study\StoreJobAnalysisRequest;
use App\Models\JobAnalysis;
use App\Services\Jobs\JobAnalysisReport;
use App\Services\Jobs\JobRecommender;
use App\Services\Jobs\SkillMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class JobAnalysisController extends Controller
{
    /** How many past analyses the history lists. */
    private const int HISTORY_SIZE = 20;

    /**
     * Show the form to paste a posting, and the user's latest analyses.
     */
    public function index(Request $request, SkillMatcher $matcher): Response
    {
        return Inertia::render('Jobs', [
            'analyses' => JobAnalysis::whereBelongsTo($request->user())
                ->latest()
                ->limit(self::HISTORY_SIZE)
                ->get()
                ->map(fn (JobAnalysis $analysis) => [
                    'id' => $analysis->id,
                    'title' => $analysis->title,
                    'created_at' => $analysis->created_at?->toIso8601String(),
                    'skills' => count($matcher->match($analysis->body)),
                ]),
        ]);
    }

    /**
     * Save a pasted posting and go to its analysis.
     */
    public function store(StoreJobAnalysisRequest $request): RedirectResponse
    {
        $analysis = JobAnalysis::create([
            'user_id' => $request->user()->id,
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
        ]);

        return to_route('jobs.show', $analysis);
    }

    /**
     * Show which roadmap modules the posting asks for, where the user stands, and what
     * to do next about it.
     */
    public function show(Request $request, JobAnalysis $analysis, JobAnalysisReport $reports, JobRecommender $recommender): Response
    {
        Gate::authorize('view', $analysis);

        $user = $request->user();
        $report = $reports->for($user, $analysis->body);

        return Inertia::render('JobShow', [
            'analysis' => [
                'id' => $analysis->id,
                'title' => $analysis->title,
                'body' => $analysis->body,
                'created_at' => $analysis->created_at?->toIso8601String(),
            ],
            'report' => $report,
            'recommendations' => $recommender->for($user, $report, JobAnalysis::whereBelongsTo($user)->count()),
        ]);
    }

    /**
     * Delete an analysis.
     */
    public function destroy(JobAnalysis $analysis): RedirectResponse
    {
        Gate::authorize('delete', $analysis);

        $analysis->delete();

        return to_route('jobs.index');
    }
}
