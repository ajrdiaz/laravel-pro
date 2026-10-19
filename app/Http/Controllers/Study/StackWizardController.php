<?php

namespace App\Http\Controllers\Study;

use App\Http\Controllers\Controller;
use App\Models\Stack;
use App\Models\User;
use App\Services\Stacks\StackAdvisor;
use App\Services\Stacks\StackPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class StackWizardController extends Controller
{
    /**
     * Ask a few questions about a project and recommend a stack. The answers travel in the
     * query string, so a result can be bookmarked or shared.
     */
    public function __invoke(Request $request, StackAdvisor $advisor, StackPresenter $presenter): Response
    {
        $answers = $request->only(['clients', 'team', 'ui', 'extras']);
        $recommendation = $advisor->recommend($answers);

        return Inertia::render('StackWizard', [
            'questions' => $advisor->questions(),
            'answers' => $advisor->sanitize($answers),
            'result' => $recommendation === null ? null : $this->result($request->user(), $recommendation, $presenter),
        ]);
    }

    /**
     * @param  array{base: list<array{slug: string, why: string}>, addons: list<array{slug: string, why: string}>}  $recommendation
     * @return array{base: list<array<string, mixed>>, addons: list<array<string, mixed>>, preparation: array{readiness: int, modules: int, startWith: list<array{slug: string, title: string, pct: int}>}}
     */
    private function result(User $user, array $recommendation, StackPresenter $presenter): array
    {
        $slugs = array_column([...$recommendation['base'], ...$recommendation['addons']], 'slug');
        $stacks = $presenter->bySlugs($slugs);
        $bySlug = $stacks->keyBy('slug');

        return [
            'base' => $this->cards($recommendation['base'], $bySlug, $presenter),
            'addons' => $this->cards($recommendation['addons'], $bySlug, $presenter),
            'preparation' => $presenter->preparation($user, $stacks),
        ];
    }

    /**
     * A card for each recommended stack that exists, with the reason it was recommended.
     *
     * @param  list<array{slug: string, why: string}>  $items
     * @param  Collection<string, Stack>  $stacks  Keyed by slug.
     * @return list<array<string, mixed>>
     */
    private function cards(array $items, Collection $stacks, StackPresenter $presenter): array
    {
        $cards = [];

        foreach ($items as $item) {
            $stack = $stacks->get($item['slug']);

            if ($stack !== null) {
                $cards[] = [...$presenter->card($stack), 'why' => $item['why']];
            }
        }

        return $cards;
    }
}
