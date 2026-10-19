<?php

namespace App\Http\Controllers\Study;

use App\Enums\StackCategory;
use App\Enums\StackLayer;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Stack;
use App\Models\StackTechnology;
use App\Services\ProgressReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StackController extends Controller
{
    /**
     * List every recommended stack, to be filtered by category on the page.
     */
    public function index(): Response
    {
        return Inertia::render('Stacks', [
            'categories' => collect(StackCategory::cases())->map(fn (StackCategory $category) => [
                'value' => $category->value,
                'label' => $category->label(),
            ]),
            'stacks' => Stack::orderBy('position')->get()->map(fn (Stack $stack) => [
                'slug' => $stack->slug,
                'name' => $stack->name,
                'summary' => $stack->summary,
                'category' => $stack->category->value,
                'headline' => $stack->headline,
                'reviewed_on' => $stack->reviewed_on->toDateString(),
            ]),
        ]);
    }

    /**
     * Show one stack: when to pick it, its layers, and which roadmap modules to master
     * first, with the user's progress in each.
     */
    public function show(Request $request, Stack $stack, ProgressReport $progress): Response
    {
        $user = $request->user();

        $stack->load(['technologies', 'modules']);

        $allModules = $progress->modules($user);
        $moduleProgress = $allModules->keyBy('id');

        return Inertia::render('StackShow', [
            'stats' => $progress->stats($user, $allModules),
            'stack' => [
                'slug' => $stack->slug,
                'name' => $stack->name,
                'summary' => $stack->summary,
                'category' => ['value' => $stack->category->value, 'label' => $stack->category->label()],
                'when_to_use' => $stack->when_to_use,
                'when_to_avoid' => $stack->when_to_avoid,
                'pros' => $stack->pros,
                'cons' => $stack->cons,
                'also_learn' => $stack->also_learn,
                'sources' => $stack->sources,
                'reviewed_on' => $stack->reviewed_on->toDateString(),
            ],
            'layers' => collect(StackLayer::cases())
                ->map(fn (StackLayer $layer) => [
                    'layer' => $layer->value,
                    'label' => $layer->label(),
                    'items' => $stack->technologies
                        ->filter(fn (StackTechnology $technology) => $technology->layer === $layer)
                        ->map(fn (StackTechnology $technology) => [
                            'name' => $technology->name,
                            'note' => $technology->note,
                        ])
                        ->values(),
                ])
                ->filter(fn (array $group) => $group['items']->isNotEmpty())
                ->values(),
            'modules' => $stack->modules->map(fn (Module $module) => [
                'slug' => $module->slug,
                'position' => $module->position,
                'command' => $module->command,
                'title' => $module->title,
                'progress' => $moduleProgress[$module->id]->toArray()['progress'],
            ]),
        ]);
    }
}
