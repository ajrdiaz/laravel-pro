<?php

namespace Tests\Feature\Study;

use App\Models\Completion;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\LessonSeeder;
use Database\Seeders\RoadmapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Tests\TestCase;

class LessonSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/lessons-'.uniqid();
        File::makeDirectory($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    private function lessonFile(string $module, string $file, string $front = "title: Un título\nsummary: Un resumen\nminutes: 5", string $body = "## Hola\n\nTexto."): void
    {
        File::ensureDirectoryExists("{$this->directory}/{$module}");
        File::put("{$this->directory}/{$module}/{$file}", "---\n{$front}\n---\n\n{$body}\n");
    }

    private function seedFrom(): void
    {
        (new LessonSeeder)->from($this->directory)->run();
    }

    public function test_it_loads_lessons_from_markdown_files()
    {
        Module::factory()->create(['slug' => 'req']);
        $this->lessonFile('req', '02-segunda.md', "title: Segunda\nsummary: Resumen dos\nminutes: 7");
        $this->lessonFile('req', '01-primera.md', "title: Primera\nsummary: Resumen uno: con dos puntos\nminutes: 4", "## Hola\n\nCuerpo de la primera.");

        $this->seedFrom();

        $lessons = Lesson::orderBy('position')->get();

        $this->assertSame(['primera', 'segunda'], $lessons->pluck('slug')->all(), 'The numeric prefix orders the lessons and is dropped from the slug.');
        $this->assertSame([1, 2], $lessons->pluck('position')->all());
        $this->assertSame('Primera', $lessons[0]->title);
        $this->assertSame('Resumen uno: con dos puntos', $lessons[0]->summary, 'Only the first colon separates key from value.');
        $this->assertSame(4, $lessons[0]->minutes);
        $this->assertSame("## Hola\n\nCuerpo de la primera.", $lessons[0]->body);
    }

    public function test_it_reads_files_saved_with_windows_line_endings()
    {
        Module::factory()->create(['slug' => 'req']);
        File::ensureDirectoryExists("{$this->directory}/req");
        File::put("{$this->directory}/req/01-windows.md", "---\r\ntitle: Windows\r\nsummary: Resumen\r\nminutes: 3\r\n---\r\n\r\n## Hola\r\n");

        $this->seedFrom();

        $this->assertSame('Windows', Lesson::firstOrFail()->title);
        $this->assertStringNotContainsString("\r", Lesson::firstOrFail()->body);
    }

    public function test_running_it_again_updates_in_place_and_keeps_progress()
    {
        $module = Module::factory()->create(['slug' => 'req']);
        $this->lessonFile('req', '01-primera.md');
        $this->seedFrom();

        $lesson = Lesson::firstOrFail();
        $user = User::factory()->create();
        Completion::create(['user_id' => $user->id, 'completable_type' => 'lesson', 'completable_id' => $lesson->id]);

        $this->lessonFile('req', '01-primera.md', "title: Título nuevo\nsummary: Resumen\nminutes: 9");
        $this->seedFrom();

        $this->assertSame(1, Lesson::count());
        $this->assertSame($lesson->id, Lesson::firstOrFail()->id);
        $this->assertSame('Título nuevo', Lesson::firstOrFail()->title);
        $this->assertSame(1, Completion::where(['user_id' => $user->id, 'completable_type' => 'lesson'])->count());
        $this->assertSame($module->id, Lesson::firstOrFail()->module_id);
    }

    public function test_reordering_lessons_keeps_their_ids()
    {
        Module::factory()->create(['slug' => 'req']);
        $this->lessonFile('req', '01-uno.md');
        $this->lessonFile('req', '02-dos.md');
        $this->seedFrom();

        $ids = Lesson::pluck('id', 'slug');

        File::move("{$this->directory}/req/01-uno.md", "{$this->directory}/req/03-uno.md");
        $this->seedFrom();

        $this->assertEquals($ids, Lesson::pluck('id', 'slug'));
        $this->assertSame(['dos', 'uno'], Lesson::orderBy('position')->pluck('slug')->all());
    }

    public function test_lessons_whose_file_is_gone_are_deleted()
    {
        Module::factory()->create(['slug' => 'req']);
        $this->lessonFile('req', '01-uno.md');
        $this->lessonFile('req', '02-dos.md');
        $this->seedFrom();

        File::delete("{$this->directory}/req/02-dos.md");
        $this->seedFrom();

        $this->assertSame(['uno'], Lesson::pluck('slug')->all());
    }

    public function test_a_directory_that_is_not_a_module_fails_loudly()
    {
        $this->lessonFile('nope', '01-uno.md');

        $this->assertSeedingFails('Unknown module [nope]');
    }

    public function test_two_files_with_the_same_slug_in_a_module_fail_loudly()
    {
        Module::factory()->create(['slug' => 'req']);
        $this->lessonFile('req', '01-igual.md');
        $this->lessonFile('req', '02-igual.md');

        $this->assertSeedingFails('two lessons with the slug [igual]');
    }

    public function test_a_file_without_front_matter_fails_loudly()
    {
        Module::factory()->create(['slug' => 'req']);
        File::ensureDirectoryExists("{$this->directory}/req");
        File::put("{$this->directory}/req/01-sin.md", "## Solo cuerpo\n");

        $this->assertSeedingFails('has no front matter');
    }

    public function test_missing_or_invalid_front_matter_fields_fail_loudly()
    {
        Module::factory()->create(['slug' => 'req']);

        $cases = [
            'missing title' => ["summary: S\nminutes: 5", '[title]'],
            'missing summary' => ["title: T\nminutes: 5", '[summary]'],
            'missing minutes' => ["title: T\nsummary: S", '[minutes]'],
            'non-numeric minutes' => ["title: T\nsummary: S\nminutes: cinco", 'positive whole number'],
            'zero minutes' => ["title: T\nsummary: S\nminutes: 0", 'positive whole number'],
        ];

        foreach ($cases as $name => [$front, $expected]) {
            File::deleteDirectory("{$this->directory}/req");
            $this->lessonFile('req', '01-caso.md', $front);

            $this->assertSeedingFails($expected, $name);
        }
    }

    public function test_a_failed_run_leaves_no_lessons_behind()
    {
        Module::factory()->create(['slug' => 'req']);
        $this->lessonFile('req', '01-buena.md');
        $this->lessonFile('req', '02-mala.md', "title: T\nsummary: S\nminutes: x");

        $this->assertSeedingFails('positive whole number');
        $this->assertSame(0, Lesson::count());
    }

    private function assertSeedingFails(string $expectedMessage, string $case = ''): void
    {
        try {
            $this->seedFrom();
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString($expectedMessage, $exception->getMessage(), $case);

            return;
        }

        $this->fail("Seeding should have failed with [{$expectedMessage}]. {$case}");
    }

    public function test_the_real_lessons_are_complete()
    {
        $this->seed([RoadmapSeeder::class, LessonSeeder::class]);

        $this->assertSame(Module::count(), Lesson::distinct('module_id')->count('module_id'), 'Every module should have at least one lesson.');

        Lesson::all()->each(function (Lesson $lesson) {
            $label = "Lesson [{$lesson->slug}]";

            $this->assertGreaterThanOrEqual(3, $lesson->minutes, $label);
            $this->assertLessThanOrEqual(20, $lesson->minutes, "$label is too long for one sitting.");
            $this->assertStringContainsString('## Practica', $lesson->body, "$label should end by pointing at the module's challenge.");
            $this->assertStringNotContainsString('<script', $lesson->html(), $label);
        });
    }
}
