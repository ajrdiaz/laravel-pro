<?php

namespace App\Http\Controllers\Study;

use App\Actions\Study\ReviewFlashcard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Study\ReviewFlashcardRequest;
use App\Models\Flashcard;
use App\Models\Module;
use App\Services\Anki\AnkiExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

class FlashcardController extends Controller
{
    /**
     * Show the review queue: the cards due today, or every card with `?all=1`.
     * `?module=<slug>` narrows it to one module.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $modules = Module::orderBy('position')->get(['id', 'slug', 'title']);
        $module = $modules->firstWhere('slug', $request->query('module'));
        $all = $request->boolean('all');

        $pool = Flashcard::query()->when($module, fn (Builder $query) => $query->where('module_id', $module?->id));

        return Inertia::render('Flashcards', [
            'modules' => $modules->map->only(['slug', 'title']),
            'filters' => ['module' => $module->slug ?? 'all', 'all' => $all],
            'total' => (clone $pool)->count(),
            'cards' => $pool
                ->when(! $all, fn (Builder $query) => $query->dueFor($user))
                ->with([
                    'module:id,title',
                    'reviews' => fn ($reviews) => $reviews->whereBelongsTo($user),
                ])
                ->inRandomOrder()
                ->get()
                ->map(fn (Flashcard $card) => [
                    'id' => $card->id,
                    'question' => $card->question,
                    'answer' => $card->answer,
                    'module' => $card->module->title,
                    'box' => $card->reviews->first()->box ?? 0,
                ]),
        ]);
    }

    /**
     * Download the flashcards as a file Anki can import. `?module=<slug>` limits it to one
     * module. The cards are the same for everyone, so this has nothing of the user's progress:
     * Anki keeps its own schedule.
     */
    public function export(Request $request, AnkiExport $anki): HttpResponse
    {
        $slug = $request->query('module');
        $module = is_string($slug) ? Module::where('slug', $slug)->firstOrFail() : null;

        $cards = Flashcard::query()
            ->when($module, fn (Builder $query) => $query->where('module_id', $module?->id))
            ->with('module:id,slug')
            ->join('modules', 'modules.id', '=', 'flashcards.module_id')
            ->orderBy('modules.position')
            ->orderBy('flashcards.position')
            ->get('flashcards.*');

        return response($anki->build($cards), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                'ruta-laravel-'.($module->slug ?? 'tarjetas').'.txt',
            ),
        ]);
    }

    /**
     * Record whether the user knew the card and schedule its next review.
     */
    public function review(ReviewFlashcardRequest $request, Flashcard $flashcard, ReviewFlashcard $action): RedirectResponse
    {
        $action->handle($request->user(), $flashcard, $request->boolean('knew'));

        return back();
    }
}
