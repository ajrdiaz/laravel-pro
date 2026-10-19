<?php

namespace Tests\Feature\Study;

use App\Models\Challenge;
use App\Models\Concept;
use App\Models\Flashcard;
use App\Models\InterviewQuestion;
use App\Models\Module;
use App\Models\QuizQuestion;
use Database\Seeders\RoadmapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ReadsContentFiles;
use Tests\TestCase;

class RoadmapSeederTest extends TestCase
{
    use ReadsContentFiles;
    use RefreshDatabase;

    public function test_it_loads_the_whole_roadmap()
    {
        $this->seed(RoadmapSeeder::class);

        $this->assertSame($this->roadmap()->count(), Module::count());
        $this->assertSame($this->roadmapTotal('concepts'), Concept::count());
        $this->assertSame($this->roadmapTotal('flashcards'), Flashcard::count());
        $this->assertSame($this->roadmapTotal('quiz'), QuizQuestion::count());
        $this->assertSame($this->roadmap()->count(), Challenge::count());
        $this->assertSame($this->roadmap()->count(), InterviewQuestion::count());
    }

    public function test_every_quiz_question_points_at_an_existing_option()
    {
        $this->seed(RoadmapSeeder::class);

        QuizQuestion::all()->each(function (QuizQuestion $question) {
            $this->assertArrayHasKey($question->correct_index, $question->options);
        });
    }

    public function test_running_it_again_updates_in_place_without_duplicating_or_changing_ids()
    {
        $this->seed(RoadmapSeeder::class);

        $ids = Concept::orderBy('id')->pluck('id');
        Concept::query()->update(['body' => 'stale']);

        $this->seed(RoadmapSeeder::class);

        $this->assertSame($this->roadmapTotal('concepts'), Concept::count());
        $this->assertEquals($ids, Concept::orderBy('id')->pluck('id'));
        $this->assertSame(0, Concept::where('body', 'stale')->count());
    }

    public function test_it_removes_rows_that_are_no_longer_in_the_content()
    {
        $this->seed(RoadmapSeeder::class);

        $module = Module::where('slug', 'req')->firstOrFail();
        $module->concepts()->create(['position' => 99, 'body' => 'obsolete']);

        $this->seed(RoadmapSeeder::class);

        $this->assertSame(0, Concept::where('body', 'obsolete')->count());
    }

    public function test_it_stores_each_modules_position_section_and_command()
    {
        $this->seed(RoadmapSeeder::class);

        $this->roadmap()->each(function (array $data) {
            $module = Module::where('slug', $data['slug'])->firstOrFail();

            $this->assertSame($data['position'], $module->position, $data['slug']);
            $this->assertSame($data['section'], $module->section, $data['slug']);
            $this->assertSame($data['command'], $module->command, $data['slug']);
        });
    }
}
