<?php

namespace App\Http\Controllers\Study;

use App\Enums\CompletableType;
use App\Enums\ResourceKind;
use App\Http\Controllers\Controller;
use App\Models\LearningResource;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Note;
use App\Models\User;
use App\Services\ProgressReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearnController extends Controller
{
    /**
     * List every module with how many lessons and resources it has to learn from.
     */
    public function index(Request $request, ProgressReport $progress): Response
    {
        $read = $progress->completedIds($request->user(), CompletableType::Lesson)->flip();

        return Inertia::render('Learn', [
            'modules' => Module::orderBy('position')
                ->with(['lessons:id,module_id', 'learningResources:id,module_id'])
                ->get()
                ->map(fn (Module $module) => [
                    'slug' => $module->slug,
                    'position' => $module->position,
                    'section' => $module->section,
                    'command' => $module->command,
                    'title' => $module->title,
                    'lessons' => [
                        'total' => $module->lessons->count(),
                        'read' => $module->lessons->filter(fn (Lesson $lesson) => $read->has($lesson->id))->count(),
                    ],
                    'resources' => $module->learningResources->count(),
                ]),
        ]);
    }

    /**
     * Show the lessons and curated resources of one module.
     */
    public function module(Request $request, Module $module, ProgressReport $progress): Response
    {
        $read = $progress->completedIds($request->user(), CompletableType::Lesson)->flip();

        $module->load(['lessons', 'learningResources']);

        return Inertia::render('LearnModule', [
            'module' => $this->moduleProps($module),
            'note' => $this->noteFor($request->user(), $module),
            'lessons' => $module->lessons->map(fn (Lesson $lesson) => [
                'slug' => $lesson->slug,
                'title' => $lesson->title,
                'summary' => $lesson->summary,
                'minutes' => $lesson->minutes,
                'completed' => $read->has($lesson->id),
            ]),
            'resources' => collect(ResourceKind::cases())
                ->map(fn (ResourceKind $kind) => [
                    'kind' => $kind->value,
                    'label' => $kind->label(),
                    'items' => $module->learningResources
                        ->filter(fn (LearningResource $resource) => $resource->kind === $kind)
                        ->map(fn (LearningResource $resource) => [
                            'id' => $resource->id,
                            'title' => $resource->title,
                            'url' => $resource->url,
                            'provider' => $resource->provider,
                            'language' => $resource->language->label(),
                            'is_free' => $resource->is_free,
                            'note' => $resource->note,
                            'embed_url' => $resource->embedUrl(),
                        ])
                        ->values(),
                ])
                ->filter(fn (array $group) => $group['items']->isNotEmpty())
                ->values(),
        ]);
    }

    /**
     * Read one lesson, with links to the previous and next one in its module.
     * The lesson is looked up inside the module, so a slug from another module is a 404.
     */
    public function lesson(Request $request, Module $module, Lesson $lesson, ProgressReport $progress): Response
    {
        $read = $progress->completedIds($request->user(), CompletableType::Lesson)->flip();

        $lessons = $module->lessons()->get(['id', 'slug', 'title']);
        $position = $lessons->search(fn (Lesson $other) => $other->is($lesson));

        // Keys run 0..n-1, so a missing neighbour (before the first, after the last) is null.
        $previous = $position === false ? null : $lessons->get($position - 1);
        $next = $position === false ? null : $lessons->get($position + 1);

        return Inertia::render('LearnLesson', [
            'module' => $this->moduleProps($module),
            'note' => $this->noteFor($request->user(), $module),
            'lesson' => [
                'id' => $lesson->id,
                'slug' => $lesson->slug,
                'title' => $lesson->title,
                'summary' => $lesson->summary,
                'minutes' => $lesson->minutes,
                'html' => $lesson->html(),
                'completed' => $read->has($lesson->id),
            ],
            'previous' => $previous?->only(['slug', 'title']),
            'next' => $next?->only(['slug', 'title']),
        ]);
    }

    /**
     * The user's own notes on the module, if they wrote any.
     */
    private function noteFor(User $user, Module $module): ?string
    {
        return Note::where(['user_id' => $user->id, 'module_id' => $module->id])->value('body');
    }

    /**
     * @return array{slug: string, position: int, command: string, title: string}
     */
    private function moduleProps(Module $module): array
    {
        return [
            'slug' => $module->slug,
            'position' => $module->position,
            'command' => $module->command,
            'title' => $module->title,
        ];
    }
}
