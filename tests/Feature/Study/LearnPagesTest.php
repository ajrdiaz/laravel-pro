<?php

namespace Tests\Feature\Study;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Services\ProgressReport;
use Database\Seeders\LearningResourceSeeder;
use Database\Seeders\LessonSeeder;
use Database\Seeders\RoadmapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ReadsContentFiles;
use Tests\TestCase;

class LearnPagesTest extends TestCase
{
    use ReadsContentFiles;
    use RefreshDatabase;

    private function seedLearning(): void
    {
        $this->seed([RoadmapSeeder::class, LessonSeeder::class, LearningResourceSeeder::class]);
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->seedLearning();

        $this->get(route('learn.index'))->assertRedirect(route('login'));
        $this->get(route('learn.module', 'req'))->assertRedirect(route('login'));
        $this->get(route('learn.lesson', ['req', 'ciclo-de-una-request']))->assertRedirect(route('login'));
    }

    public function test_the_index_counts_lessons_and_resources_per_module()
    {
        $this->seedLearning();

        $lessonFiles = fn (string $slug) => count(glob(database_path("data/lessons/{$slug}/*.md")));
        $resources = $this->resourcesFile()->mapWithKeys(fn (array $group) => [$group['module'] => count($group['items'])]);

        $this->actingAs(User::factory()->create())->get(route('learn.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learn')
                ->has('modules', $this->roadmap()->count())
                ->where('modules', fn ($modules) => collect($modules)->pluck('slug')->all() === $this->roadmap()->pluck('slug')->all())
                ->where('modules', fn ($modules) => collect($modules)->every(
                    fn ($module) => $module['lessons'] === ['total' => $lessonFiles($module['slug']), 'read' => 0]
                        && $module['resources'] === $resources[$module['slug']]
                        && $module['section'] !== '',
                )));
    }

    public function test_a_module_page_lists_its_lessons_and_groups_resources_by_kind()
    {
        $this->seedLearning();

        $this->actingAs(User::factory()->create())->get(route('learn.module', 'req'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('LearnModule')
                ->where('module.slug', 'req')
                ->has('lessons', 2)
                ->where('lessons.0.slug', 'ciclo-de-una-request')
                ->where('lessons.1.slug', 'de-excepcion-a-respuesta')
                ->where('lessons.0.completed', false)
                ->where('resources', fn ($groups) => collect($groups)->pluck('kind')->all() === ['docs', 'course'])
                ->has('resources.0.items', 4)
                ->where('resources.0.label', 'Documentación')
                ->where('resources.0.items.0.language', 'Inglés')
                ->where('resources.0.items.0.embed_url', null));
    }

    public function test_youtube_videos_come_with_an_embed_url()
    {
        $this->seedLearning();

        $this->actingAs(User::factory()->create())->get(route('learn.module', 'rt'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('resources.1.kind', 'video')
                ->where('resources.1.items.0.embed_url', 'https://www.youtube-nocookie.com/embed/gv9z1yv0_7k')
                ->where('resources.1.items.0.is_free', true));
    }

    public function test_a_lesson_page_renders_the_markdown_and_marks_where_it_sits_in_the_module()
    {
        $module = Module::factory()->create();
        [$first, $second, $third] = Lesson::factory()->count(3)->sequence(
            ['position' => 1, 'slug' => 'uno', 'body' => "## Uno\n\nTexto con `código`."],
            ['position' => 2, 'slug' => 'dos'],
            ['position' => 3, 'slug' => 'tres'],
        )->create(['module_id' => $module->id]);

        $this->actingAs(User::factory()->create())->get(route('learn.lesson', [$module->slug, 'uno']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('LearnLesson')
                ->where('module.slug', $module->slug)
                ->where('lesson.slug', 'uno')
                ->where('lesson.completed', false)
                ->where('lesson.html', fn ($html) => str_contains($html, '<h2>Uno</h2>') && str_contains($html, '<code>código</code>'))
                ->where('previous', null)
                ->where('next', ['slug' => 'dos', 'title' => $second->title]));

        $this->actingAs(User::factory()->create())->get(route('learn.lesson', [$module->slug, 'dos']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('previous', ['slug' => 'uno', 'title' => $first->title])
                ->where('next', ['slug' => 'tres', 'title' => $third->title]));

        $this->actingAs(User::factory()->create())->get(route('learn.lesson', [$module->slug, 'tres']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('previous', ['slug' => 'dos', 'title' => $second->title])
                ->where('next', null));
    }

    public function test_a_lesson_is_only_found_inside_its_own_module()
    {
        $mine = Module::factory()->create();
        $other = Module::factory()->create();
        Lesson::factory()->create(['module_id' => $other->id, 'slug' => 'ajena']);

        $this->actingAs(User::factory()->create())
            ->get(route('learn.lesson', [$mine->slug, 'ajena']))
            ->assertNotFound();
    }

    public function test_unknown_modules_and_lessons_are_not_found()
    {
        $module = Module::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('learn.module', 'nope'))->assertNotFound();
        $this->actingAs($user)->get(route('learn.lesson', [$module->slug, 'nope']))->assertNotFound();
    }

    public function test_reading_a_lesson_is_ticked_by_hand_and_shows_on_every_learn_page()
    {
        $module = Module::factory()->create();
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'slug' => 'leccion']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('completions.update', ['lesson', $lesson->id]), ['completed' => true])
            ->assertRedirect();

        $this->actingAs($user)->get(route('learn.lesson', [$module->slug, 'leccion']))
            ->assertInertia(fn (Assert $page) => $page->where('lesson.completed', true));
        $this->actingAs($user)->get(route('learn.module', $module->slug))
            ->assertInertia(fn (Assert $page) => $page->where('lessons.0.completed', true));
        $this->actingAs($user)->get(route('learn.index'))
            ->assertInertia(fn (Assert $page) => $page->where('modules.0.lessons', ['total' => 1, 'read' => 1]));

        $this->actingAs($user)
            ->put(route('completions.update', ['lesson', $lesson->id]), ['completed' => false]);

        $this->actingAs($user)->get(route('learn.index'))
            ->assertInertia(fn (Assert $page) => $page->where('modules.0.lessons.read', 0));
    }

    public function test_lessons_count_towards_the_module_progress()
    {
        $module = Module::factory()->create();
        $lessons = Lesson::factory()->count(2)->create(['module_id' => $module->id]);
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('completions.update', ['lesson', $lessons[0]->id]), ['completed' => true]);

        $progress = (new ProgressReport)->modules($user)->first()->toArray()['progress'];

        $this->assertSame(['done' => 1, 'total' => 2, 'pct' => 50], $progress);
    }

    public function test_the_markdown_is_safe_to_inject_into_the_page()
    {
        $lesson = Lesson::factory()->create([
            'body' => <<<'MD'
                <script>alert(1)</script>

                <img src=x onerror="alert(2)">

                [enlace malo](javascript:alert(3))

                [enlace bueno](/stacks/rest-api)
                MD,
        ]);

        $html = $lesson->html();

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('href="/stacks/rest-api"', $html);
    }

    public function test_the_markdown_keeps_code_fences_and_tables()
    {
        $lesson = Lesson::factory()->create([
            'body' => "```php\necho 1;\n```\n\n| A | B |\n| --- | --- |\n| 1 | 2 |\n",
        ]);

        $html = $lesson->html();

        $this->assertStringContainsString('<pre><code class="language-php">', $html);
        $this->assertStringContainsString('<table>', $html);
    }
}
