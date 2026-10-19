<?php

namespace Tests\Feature\Study;

use App\Enums\CompletableType;
use App\Models\Completion;
use App\Models\JobAnalysis;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use App\Services\Jobs\JobAnalysisReport;
use App\Services\Jobs\JobRecommender;
use Database\Seeders\LessonSeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\RoadmapSeeder;
use Database\Seeders\StackSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CompletesModules;
use Tests\TestCase;

class JobRecommenderTest extends TestCase
{
    use CompletesModules;
    use RefreshDatabase;

    private const string API_POSTING = 'Buscamos backend Laravel con experiencia en APIs REST, Sanctum, Eloquent, MySQL, Pest y GitHub Actions.';

    private const string INERTIA_POSTING = 'Fullstack con Laravel, Inertia, Vue 3, TypeScript, Tailwind, Pest y despliegue con Docker.';

    private const string REALTIME_POSTING = 'Aplicación con WebSockets: Laravel Reverb, colas con Redis, eventos y notificaciones en vivo.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoadmapSeeder::class, LessonSeeder::class, StackSeeder::class, ProjectSeeder::class]);
    }

    /**
     * @return list<array{type: string, title: string, body: string, action: array{label: string, href: string}|null}>
     */
    private function recommend(User $user, string $posting, int $postings = 1): array
    {
        $report = app(JobAnalysisReport::class)->for($user, $posting);

        return app(JobRecommender::class)->for($user, $report, $postings);
    }

    /**
     * @param  list<array{type: string, title: string, body: string, action: array{label: string, href: string}|null}>  $recommendations
     * @return array{type: string, title: string, body: string, action: array{label: string, href: string}|null}|null
     */
    private function of(array $recommendations, string $type): ?array
    {
        foreach ($recommendations as $recommendation) {
            if ($recommendation['type'] === $type) {
                return $recommendation;
            }
        }

        return null;
    }

    /**
     * A report with the given readiness and one module in each of the given statuses, for
     * the rules that only look at those numbers.
     *
     * @param  list<string>  $statuses
     * @return array{summary: array{detected: int, covered: int, outside: int, readiness: int|null}, modules: list<array{slug: string, title: string, section: string, command: string, status: string, mentions: int, progress: array{done: int, total: int, pct: int}, skills: list<array{name: string, mentions: int, note: string|null}>}>, outside: list<array{name: string, mentions: int, category: string|null, hint: string|null}>}
     */
    private function reportWith(?int $readiness, array $statuses = []): array
    {
        return [
            'summary' => ['detected' => count($statuses), 'covered' => count($statuses), 'outside' => 0, 'readiness' => $readiness],
            'modules' => array_map(fn (string $status, int $index) => [
                'slug' => "no-such-module-{$index}",
                'title' => "Módulo {$index}",
                'section' => 'Fundamentos',
                'command' => 'php artisan about',
                'status' => $status,
                'mentions' => 1,
                'progress' => ['done' => 0, 'total' => 10, 'pct' => 0],
                'skills' => [],
            ], $statuses, array_keys($statuses)),
            'outside' => [],
        ];
    }

    public function test_the_readiness_message_follows_how_close_the_user_is()
    {
        $user = User::factory()->create();
        $recommender = app(JobRecommender::class);

        $title = fn (int $readiness) => $this->of($recommender->for($user, $this->reportWith($readiness, ['todo']), 3), 'readiness')['title'];

        $this->assertSame('Úsala como mapa, no como meta', $title(0));
        $this->assertSame('Úsala como mapa, no como meta', $title(24));
        $this->assertSame('Ve cerrando los módulos pendientes', $title(25));
        $this->assertSame('Ve cerrando los módulos pendientes', $title(69));
        $this->assertSame('Estás cerca', $title(70));
        $this->assertSame('Estás cerca', $title(100));
    }

    public function test_the_middle_message_counts_the_modules_not_started_yet()
    {
        $user = User::factory()->create();
        $recommender = app(JobRecommender::class);

        $withTodo = $this->of($recommender->for($user, $this->reportWith(40, ['todo', 'todo', 'learning']), 3), 'readiness');
        $withoutTodo = $this->of($recommender->for($user, $this->reportWith(40, ['learning', 'learning']), 3), 'readiness');

        $this->assertStringContainsString('empieza los 2 módulos que aún no has tocado', $withTodo['body']);
        $this->assertStringNotContainsString('aún no has tocado', $withoutTodo['body']);
        $this->assertStringContainsString('termina los módulos que llevas a medias', $withoutTodo['body']);
    }

    public function test_there_is_no_readiness_message_when_nothing_in_the_route_was_found()
    {
        $user = User::factory()->create();

        $recommendations = app(JobRecommender::class)->for($user, $this->reportWith(null), 3);

        $this->assertNull($this->of($recommendations, 'readiness'));
    }

    public function test_a_realistic_posting_gets_each_kind_of_advice_in_a_fixed_order()
    {
        $user = User::factory()->create();

        $types = array_column($this->recommend($user, self::API_POSTING), 'type');

        // A new user has nothing to review, and this posting asks for nothing outside the route.
        $this->assertSame(['readiness', 'lesson', 'project', 'stack', 'postings'], $types);
    }

    public function test_every_link_is_a_path_inside_the_app()
    {
        $recommendations = $this->recommend(User::factory()->create(), self::INERTIA_POSTING);

        foreach ($recommendations as $recommendation) {
            if ($recommendation['action'] !== null) {
                $this->assertStringStartsWith('/', $recommendation['action']['href']);
                $this->assertNotSame('', $recommendation['action']['label']);
            }
        }
    }

    public function test_the_next_lesson_is_the_first_unread_one_of_the_module_the_posting_stresses_most()
    {
        $user = User::factory()->create();
        $posting = 'Pruebas automatizadas con Pest. Testing con Pest en cada entrega.';

        $lessons = Module::where('slug', 'test')->firstOrFail()->lessons()->get();

        $first = $this->of($this->recommend($user, $posting), 'lesson');

        $this->assertSame("/learn/test/{$lessons[0]->slug}", $first['action']['href']);
        $this->assertStringContainsString($lessons[0]->title, $first['body']);

        Completion::create(['user_id' => $user->id, 'completable_type' => CompletableType::Lesson->value, 'completable_id' => $lessons[0]->id]);

        $second = $this->of($this->recommend($user, $posting), 'lesson');

        $this->assertSame("/learn/test/{$lessons[1]->slug}", $second['action']['href']);
    }

    public function test_there_is_no_lesson_to_suggest_once_the_module_is_read_or_mastered()
    {
        $user = User::factory()->create();
        $posting = 'Pruebas automatizadas con Pest.';

        $this->completeModule($user, 'test');

        $this->assertNull($this->of($this->recommend($user, $posting), 'lesson'));
    }

    public function test_the_lesson_moves_on_to_the_next_module_when_the_first_has_nothing_left_to_read()
    {
        $user = User::factory()->create();
        $posting = 'Laravel con Pest, Pest, Pest y Docker.';

        // Pest is what the posting stresses most; Docker comes second.
        foreach (Module::where('slug', 'test')->firstOrFail()->lessons as $lesson) {
            Completion::create(['user_id' => $user->id, 'completable_type' => CompletableType::Lesson->value, 'completable_id' => $lesson->id]);
        }

        $lesson = $this->of($this->recommend($user, $posting), 'lesson');

        $this->assertNotNull($lesson);
        $this->assertStringNotContainsString('/learn/test/', $lesson['action']['href']);
    }

    public function test_it_suggests_the_project_that_practises_most_of_what_the_posting_asks()
    {
        $user = User::factory()->create();

        $api = $this->of($this->recommend($user, self::API_POSTING), 'project');
        $inertia = $this->of($this->recommend($user, self::INERTIA_POSTING), 'project');
        $realtime = $this->of($this->recommend($user, self::REALTIME_POSTING), 'project');

        $this->assertSame('/projects/api-de-tareas', $api['action']['href']);
        $this->assertSame('/projects/tablero-de-candidaturas', $inertia['action']['href']);
        $this->assertSame('/projects/chat-de-equipo', $realtime['action']['href']);

        $this->assertStringContainsString('unas 12 h', $api['body']);
        $this->assertStringContainsString('Nivel Básico', $api['body']);
    }

    public function test_it_needs_at_least_two_shared_modules_to_suggest_a_project()
    {
        $this->assertNull($this->of($this->recommend(User::factory()->create(), 'Se busca programador PHP.'), 'project'));
    }

    public function test_a_finished_project_is_not_suggested_again()
    {
        $user = User::factory()->create();

        $api = Project::where('slug', 'api-de-tareas')->with('milestones')->firstOrFail();

        $this->assertSame('/projects/api-de-tareas', $this->of($this->recommend($user, self::API_POSTING), 'project')['action']['href']);

        foreach ($api->milestones as $milestone) {
            Completion::create(['user_id' => $user->id, 'completable_type' => CompletableType::ProjectMilestone->value, 'completable_id' => $milestone->id]);
        }

        $project = $this->of($this->recommend($user, self::API_POSTING), 'project');

        $this->assertNotSame('/projects/api-de-tareas', $project['action']['href'] ?? null);
    }

    public function test_it_names_the_modules_the_posting_stresses_most_when_it_suggests_a_project()
    {
        $project = $this->of($this->recommend(User::factory()->create(), 'Docker, Docker y Docker para desplegar. También Vue, Inertia, TypeScript y Pest.'), 'project');

        $this->assertStringContainsString('Docker y despliegue', $project['body']);
    }

    public function test_it_suggests_the_stack_the_posting_describes()
    {
        $user = User::factory()->create();

        $api = $this->of($this->recommend($user, self::API_POSTING), 'stack');
        $inertia = $this->of($this->recommend($user, self::INERTIA_POSTING), 'stack');

        $this->assertSame('/stacks/rest-api', $api['action']['href']);
        $this->assertSame('/stacks/inertia-vue-react', $inertia['action']['href']);
        $this->assertMatchesRegularExpression('/la oferta pide \d+ de sus \d+ módulos/', $api['body']);
    }

    public function test_it_needs_at_least_three_shared_modules_to_suggest_a_stack()
    {
        $user = User::factory()->create();

        $this->assertNull($this->of($this->recommend($user, 'Se busca programador PHP.'), 'stack'));
        $this->assertNull($this->of($this->recommend($user, 'Experiencia con AWS, Kubernetes y Terraform.'), 'stack'));
    }

    public function test_it_suggests_reviewing_a_module_only_once_the_user_has_started_it()
    {
        $user = User::factory()->create();
        $posting = 'Laravel con Inertia y Vue.';

        $this->assertNull($this->of($this->recommend($user, $posting), 'review'));

        $this->completeModule($user, 'ine');

        $review = $this->of($this->recommend($user, $posting), 'review');

        $this->assertSame('/flashcards?module=ine', $review['action']['href']);
        $this->assertStringContainsString('Ya llevas el 100%', $review['body']);
    }

    public function test_it_points_at_what_the_route_does_not_cover_with_the_hint_for_it()
    {
        $recommendations = $this->recommend(User::factory()->create(), 'Experiencia con AWS, Kubernetes y Terraform.');

        $outside = $this->of($recommendations, 'outside');

        $this->assertStringContainsString('AWS', $outside['body']);
        $this->assertStringContainsString('Empieza por AWS', $outside['body']);
        $this->assertStringContainsString('documentación de AWS', $outside['body'], 'The hint of the skill should come along.');
        $this->assertNull($outside['action']);
    }

    public function test_a_single_topic_outside_the_route_is_worded_in_the_singular()
    {
        $outside = $this->of($this->recommend(User::factory()->create(), 'Se valora conocer Terraform.'), 'outside');

        $this->assertStringContainsString('un tema que ningún módulo enseña todavía: Terraform', $outside['body']);
    }

    public function test_nothing_is_said_about_the_route_when_the_posting_stays_inside_it()
    {
        $this->assertNull($this->of($this->recommend(User::factory()->create(), self::API_POSTING), 'outside'));
    }

    public function test_it_suggests_analysing_more_postings_until_there_are_three()
    {
        $user = User::factory()->create();

        foreach ([1, 2] as $saved) {
            $this->assertNotNull($this->of($this->recommend($user, self::API_POSTING, $saved), 'postings'));
        }

        $this->assertNull($this->of($this->recommend($user, self::API_POSTING, 3), 'postings'));
        $this->assertSame('/jobs', $this->of($this->recommend($user, self::API_POSTING, 1), 'postings')['action']['href']);
    }

    public function test_a_posting_with_nothing_the_route_covers_only_gets_the_general_advice()
    {
        $types = array_column($this->recommend(User::factory()->create(), 'Texto sin ninguna tecnología reconocible.'), 'type');

        $this->assertSame(['postings'], $types);
    }

    public function test_the_report_page_brings_the_recommendations_and_counts_the_saved_postings()
    {
        $user = User::factory()->create();
        $analysis = JobAnalysis::factory()->create(['user_id' => $user->id, 'body' => self::API_POSTING]);

        $this->actingAs($user)->get(route('jobs.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page
                ->component('JobShow')
                ->has('recommendations', 5)
                ->where('recommendations.0.type', 'readiness')
                ->where('recommendations', fn ($items) => collect($items)->pluck('type')->contains('postings'))
                ->has('recommendations.2', fn (Assert $item) => $item
                    ->where('type', 'project')
                    ->hasAll(['title', 'body', 'action.label', 'action.href'])));

        // With two more saved, the suggestion to analyse more postings goes away.
        JobAnalysis::factory()->count(2)->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('jobs.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recommendations', fn ($items) => ! collect($items)->pluck('type')->contains('postings')));
    }

    public function test_the_recommendations_follow_the_progress_of_whoever_looks_at_the_report()
    {
        $reader = User::factory()->create();
        $other = User::factory()->create();
        $analysis = JobAnalysis::factory()->create(['user_id' => $reader->id, 'body' => self::INERTIA_POSTING]);

        $this->completeModule($reader, 'ine');

        $this->actingAs($reader)->get(route('jobs.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recommendations', fn ($items) => collect($items)->pluck('type')->contains('review')));

        // Someone else's progress never shows up in it.
        $this->completeModule($other, 'vue');

        $this->actingAs($reader)->get(route('jobs.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recommendations', fn ($items) => str_contains(collect($items)->firstWhere('type', 'review')['action']['href'], 'module=ine')));
    }
}
