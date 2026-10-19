<?php

namespace App\Services\Jobs;

use App\Enums\CompletableType;
use App\Models\Lesson;
use App\Models\Project;
use App\Models\Stack;
use App\Models\User;
use App\Services\ModuleProgress;
use App\Services\ProgressReport;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turns the analysis of a job posting into a handful of next steps, using what the app
 * already knows: the lessons the user has read, the projects and stacks in the catalogue
 * and how far they are in each module. The rules are plain and deterministic, so they can
 * be tested one by one and nothing leaves the server.
 *
 * @phpstan-import-type Report from JobAnalysisReport
 *
 * @phpstan-type Recommendation array{type: string, title: string, body: string, action: array{label: string, href: string}|null}
 */
class JobRecommender
{
    /** Below this readiness, the posting is a map to follow rather than something to apply to. */
    private const int LOW_READINESS = 25;

    /** A project must practise at least this many of the posting's modules to be suggested. */
    private const int MIN_PROJECT_OVERLAP = 2;

    /** A stack must share at least this many modules with the posting to be suggested. */
    private const int MIN_STACK_OVERLAP = 3;

    /** Fewer saved postings than this and comparing more of them is worth suggesting. */
    private const int ENOUGH_POSTINGS = 3;

    public function __construct(private readonly ProgressReport $progress) {}

    /**
     * @param  Report  $report
     * @param  int  $postings  How many postings the user has saved, this one included.
     * @return list<Recommendation>
     */
    public function for(User $user, array $report, int $postings): array
    {
        return array_values(array_filter([
            $this->readiness($report),
            $this->nextLesson($user, $report['modules']),
            $this->project($user, $report['modules']),
            $this->stack($report['modules']),
            $this->review($report['modules']),
            $this->outsideTheRoute($report['outside']),
            $this->morePostings($postings),
        ]));
    }

    /**
     * How close the user is, in plain words.
     *
     * @param  Report  $report
     * @return Recommendation|null
     */
    private function readiness(array $report): ?array
    {
        $readiness = $report['summary']['readiness'];

        if ($readiness === null) {
            return null;
        }

        $toStart = count(array_filter($report['modules'], fn (array $module) => $module['status'] === 'todo'));

        [$title, $body] = match (true) {
            $readiness < self::LOW_READINESS => [
                'Úsala como mapa, no como meta',
                "Hoy cubres el {$readiness}% de lo que pide. Falta bastante para postular a esta oferta: tómala como guía de qué aprender y busca también ofertas de un nivel más básico.",
            ],
            $readiness < ModuleProgress::READY_PERCENTAGE => [
                'Ve cerrando los módulos pendientes',
                $toStart > 0
                    ? "Cubres el {$readiness}%. Antes de postular, empieza los {$toStart} módulos que aún no has tocado y termina los que llevas a medias."
                    : "Cubres el {$readiness}%. Antes de postular, termina los módulos que llevas a medias.",
            ],
            default => [
                'Estás cerca',
                "Cubres el {$readiness}%. Repasa lo que ya tienes y prepara la entrevista: postular ya tiene sentido.",
            ],
        };

        return ['type' => 'readiness', 'title' => $title, 'body' => $body, 'action' => null];
    }

    /**
     * The first unread lesson of the module the posting stresses most, among those the
     * user has not mastered yet.
     *
     * @param  Report['modules']  $modules
     * @return Recommendation|null
     */
    private function nextLesson(User $user, array $modules): ?array
    {
        $slugs = [];

        foreach ($modules as $module) {
            if ($module['status'] !== 'ready') {
                $slugs[] = $module['slug'];
            }
        }

        if ($slugs === []) {
            return null;
        }

        $read = $this->progress->completedIds($user, CompletableType::Lesson)->flip();

        $lessons = Lesson::query()
            ->whereHas('module', fn (Builder $query) => $query->whereIn('slug', $slugs))
            ->with('module:id,slug')
            ->orderBy('position')
            ->get()
            ->groupBy(fn (Lesson $lesson) => $lesson->module->slug);

        foreach ($modules as $module) {
            $lesson = $lessons->get($module['slug'])?->first(fn (Lesson $candidate) => ! $read->has($candidate->id));

            if ($lesson === null) {
                continue;
            }

            $mentions = $module['mentions'] === 1 ? 'una vez' : "{$module['mentions']} veces";

            return [
                'type' => 'lesson',
                'title' => 'Tu siguiente lección',
                'body' => "«{$lesson->title}» ({$lesson->minutes} min) abre {$module['title']}, un tema que la oferta menciona {$mentions}.",
                'action' => [
                    'label' => 'Leer la lección',
                    'href' => route('learn.lesson', [$module['slug'], $lesson->slug], absolute: false),
                ],
            ];
        }

        return null;
    }

    /**
     * The portfolio project that practises what the posting stresses most: each shared
     * module counts as many times as the posting mentions it, and half if the user already
     * has it. A project they finished is not suggested again.
     *
     * @param  Report['modules']  $modules
     * @return Recommendation|null
     */
    private function project(User $user, array $modules): ?array
    {
        $weights = [];

        foreach ($modules as $module) {
            $weights[$module['slug']] = $module['mentions'] * ($module['status'] === 'ready' ? 0.5 : 1.0);
        }

        $ticked = $this->progress->completedIds($user, CompletableType::ProjectMilestone)->flip();

        $best = null;
        $bestScore = 0.0;
        $bestModules = collect();

        foreach (Project::with(['modules:id,slug,title', 'milestones:id,project_id'])->orderBy('position')->get() as $project) {
            $finished = $project->milestones->isNotEmpty()
                && $project->milestones->every(fn ($milestone) => $ticked->has($milestone->id));

            $shared = $project->modules->filter(fn ($module) => isset($weights[$module->slug]));

            if ($finished || $shared->count() < self::MIN_PROJECT_OVERLAP) {
                continue;
            }

            $score = $shared->sum(fn ($module) => $weights[$module->slug]);

            if ($score > $bestScore) {
                [$best, $bestScore, $bestModules] = [$project, $score, $shared];
            }
        }

        if ($best === null) {
            return null;
        }

        // Name the ones the posting stresses most, not the first ones in the roadmap.
        $titles = $bestModules->sortByDesc(fn ($module) => $weights[$module->slug])->take(3)->pluck('title')->implode(', ');

        return [
            'type' => 'project',
            'title' => 'Un proyecto para tu portafolio',
            'body' => "«{$best->title}» practica {$bestModules->count()} de los módulos de esta oferta ({$titles}). Nivel {$best->level->label()}, unas {$best->hours} h.",
            'action' => [
                'label' => 'Ver el proyecto',
                'href' => route('projects.show', $best->slug, absolute: false),
            ],
        ];
    }

    /**
     * The stack that shares the most modules with the posting, as the kind of application
     * it describes.
     *
     * @param  Report['modules']  $modules
     * @return Recommendation|null
     */
    private function stack(array $modules): ?array
    {
        $mentions = array_column($modules, 'mentions', 'slug');

        $best = null;
        $bestShared = 0;
        $bestMentions = 0;

        foreach (Stack::with('modules:id,slug')->orderBy('position')->get() as $stack) {
            $shared = $stack->modules->filter(fn ($module) => isset($mentions[$module->slug]));
            $stressed = $shared->sum(fn ($module) => $mentions[$module->slug]);

            if ($shared->count() < self::MIN_STACK_OVERLAP) {
                continue;
            }

            if ([$shared->count(), $stressed] > [$bestShared, $bestMentions]) {
                [$best, $bestShared, $bestMentions] = [$stack, $shared->count(), $stressed];
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            'type' => 'stack',
            'title' => 'El tipo de aplicación que describe',
            'body' => "Encaja con «{$best->name}»: la oferta pide {$bestShared} de sus {$best->modules->count()} módulos. {$best->summary}",
            'action' => [
                'label' => 'Ver el stack',
                'href' => route('stacks.show', $best->slug, absolute: false),
            ],
        ];
    }

    /**
     * Keeping fresh what the user has already started, starting with the module the
     * posting stresses most.
     *
     * @param  Report['modules']  $modules
     * @return Recommendation|null
     */
    private function review(array $modules): ?array
    {
        foreach ($modules as $module) {
            if ($module['progress']['pct'] === 0) {
                continue;
            }

            return [
                'type' => 'review',
                'title' => 'Repasa lo que ya llevas',
                'body' => "Ya llevas el {$module['progress']['pct']}% de {$module['title']}. Repasa sus tarjetas antes de la entrevista para no perderlo.",
                'action' => [
                    'label' => 'Repasar las tarjetas',
                    'href' => route('flashcards.index', ['module' => $module['slug']], absolute: false),
                ],
            ];
        }

        return null;
    }

    /**
     * What no module teaches yet, starting with the topic mentioned most.
     *
     * @param  Report['outside']  $outside
     * @return Recommendation|null
     */
    private function outsideTheRoute(array $outside): ?array
    {
        if ($outside === []) {
            return null;
        }

        $names = implode(', ', array_column(array_slice($outside, 0, 3), 'name'));
        $first = $outside[0];
        $count = count($outside);

        $body = $count === 1
            ? "La oferta pide un tema que ningún módulo enseña todavía: {$first['name']}."
            : "La oferta pide {$count} temas que ningún módulo enseña todavía ({$names}). Empieza por {$first['name']}, el más mencionado.";

        if ($first['hint'] !== null) {
            $body .= " {$first['hint']}";
        }

        return ['type' => 'outside', 'title' => 'Lo que ningún módulo enseña todavía', 'body' => $body, 'action' => null];
    }

    /**
     * One posting is anecdote; a few are a pattern.
     *
     * @return Recommendation|null
     */
    private function morePostings(int $postings): ?array
    {
        if ($postings >= self::ENOUGH_POSTINGS) {
            return null;
        }

        return [
            'type' => 'postings',
            'title' => 'Analiza más ofertas del mismo puesto',
            'body' => 'Una sola oferta puede pedir cosas raras. Con dos o tres más verás qué se repite, y eso es lo que de verdad piden.',
            'action' => [
                'label' => 'Analizar otra oferta',
                'href' => route('jobs.index', absolute: false),
            ],
        ];
    }
}
