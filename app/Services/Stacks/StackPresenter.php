<?php

namespace App\Services\Stacks;

use App\Enums\StackLayer;
use App\Models\Stack;
use App\Models\StackTechnology;
use App\Models\User;
use App\Services\ModuleProgress;
use App\Services\ProgressReport;
use Illuminate\Support\Collection;

/**
 * Shapes stacks for the pages that compare or recommend them, adding how prepared
 * the user is for each one.
 */
class StackPresenter
{
    /** How many modules the "start with" list names at most. */
    private const int START_WITH = 5;

    public function __construct(private readonly ProgressReport $progress) {}

    /**
     * How prepared a user is for a set of stacks: the average progress over every module
     * they need, and the modules furthest from done, to start with.
     *
     * @param  Collection<int, Stack>  $stacks  With their `modules` loaded.
     * @return array{readiness: int, modules: int, startWith: list<array{slug: string, title: string, pct: int}>}
     */
    public function preparation(User $user, Collection $stacks): array
    {
        $progress = $this->progress->modules($user)->keyBy('slug');

        // Keyed by slug, so a module two stacks both need is counted once.
        $needed = [];

        foreach ($stacks as $stack) {
            foreach ($stack->modules as $module) {
                $found = $progress->get($module->slug);

                if ($found !== null) {
                    $needed[$module->slug] = $found;
                }
            }
        }

        if ($needed === []) {
            return ['readiness' => 0, 'modules' => 0, 'startWith' => []];
        }

        $pending = array_values(array_filter($needed, fn (ModuleProgress $module) => ! $module->isReady()));

        // Furthest from done first; the roadmap order keeps ties stable.
        usort($pending, fn (ModuleProgress $a, ModuleProgress $b) => [$a->percentage(), $a->position] <=> [$b->percentage(), $b->position]);

        $startWith = [];

        foreach (array_slice($pending, 0, self::START_WITH) as $module) {
            $startWith[] = ['slug' => $module->slug, 'title' => $module->title, 'pct' => $module->percentage()];
        }

        return [
            'readiness' => (int) round(array_sum(array_map(fn (ModuleProgress $module) => $module->percentage(), $needed)) / count($needed)),
            'modules' => count($needed),
            'startWith' => $startWith,
        ];
    }

    /**
     * The summary card used when a stack is recommended.
     *
     * @return array{slug: string, name: string, summary: string, category: string, headline: list<string>}
     */
    public function card(Stack $stack): array
    {
        return [
            'slug' => $stack->slug,
            'name' => $stack->name,
            'summary' => $stack->summary,
            'category' => $stack->category->label(),
            'headline' => $stack->headline,
        ];
    }

    /**
     * Everything the comparison table shows about one stack.
     *
     * @return array<string, mixed>
     */
    public function column(User $user, Stack $stack): array
    {
        $stack->loadMissing(['technologies', 'modules']);

        $preparation = $this->preparation($user, collect([$stack]));

        return [
            ...$this->card($stack),
            'when_to_use' => $stack->when_to_use,
            'when_to_avoid' => $stack->when_to_avoid,
            'pros' => $stack->pros,
            'cons' => $stack->cons,
            'readiness' => $preparation['readiness'],
            'modules' => $preparation['modules'],
            // Technology names per layer, so a table can line the stacks up row by row.
            'layers' => $stack->technologies
                ->groupBy(fn (StackTechnology $technology) => $technology->layer->value)
                ->map(fn (Collection $technologies) => $technologies->pluck('name')->values()->all())
                ->all(),
        ];
    }

    /**
     * The layers any of the given stacks use, in the fixed display order.
     *
     * @param  Collection<int, Stack>  $stacks  With their `technologies` loaded.
     * @return list<array{layer: string, label: string}>
     */
    public function layersUsedBy(Collection $stacks): array
    {
        $used = [];

        foreach ($stacks as $stack) {
            foreach ($stack->technologies as $technology) {
                $used[$technology->layer->value] = true;
            }
        }

        $layers = [];

        foreach (StackLayer::cases() as $layer) {
            if (isset($used[$layer->value])) {
                $layers[] = ['layer' => $layer->value, 'label' => $layer->label()];
            }
        }

        return $layers;
    }

    /**
     * Load stacks by slug, keeping the order of the slugs given.
     *
     * @param  list<string>  $slugs
     * @return Collection<int, Stack>
     */
    public function bySlugs(array $slugs): Collection
    {
        $stacks = Stack::whereIn('slug', $slugs)->with(['technologies', 'modules'])->get()->keyBy('slug');

        return collect($slugs)->map(fn (string $slug) => $stacks->get($slug))->filter()->values();
    }

    /**
     * Every stack, for a picker.
     *
     * @return Collection<int, array{slug: string, name: string}>
     */
    public function options(): Collection
    {
        return Stack::orderBy('position')->get(['slug', 'name'])->map(fn (Stack $stack) => ['slug' => $stack->slug, 'name' => $stack->name]);
    }
}
