<?php

namespace Database\Seeders;

use App\Models\Module;
use Database\Seeders\Concerns\SyncsPositionedRows;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RoadmapSeeder extends Seeder
{
    use SyncsPositionedRows;

    /**
     * Sync the roadmap content from database/data/roadmap.json.
     *
     * Modules are matched by slug and everything else by position within its module, so
     * running it again updates content in place and keeps users' progress.
     */
    public function run(): void
    {
        /** @var list<array<string, mixed>> $modules */
        $modules = json_decode(File::get(database_path('data/roadmap.json')), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($modules) {
            foreach ($modules as $data) {
                $module = Module::updateOrCreate(
                    ['slug' => $data['slug']],
                    [
                        'position' => $data['position'],
                        'section' => $data['section'],
                        'command' => $data['command'],
                        'title' => $data['title'],
                    ],
                );

                $this->syncRows($module->concepts(), array_values(array_map(
                    fn (string $body) => ['body' => $body],
                    $data['concepts'],
                )));
                $this->syncRows($module->flashcards(), $data['flashcards']);
                $this->syncRows($module->quizQuestions(), $data['quiz']);
                $this->syncRows($module->challenges(), [['body' => $data['challenge']]]);
                $this->syncRows($module->interviewQuestions(), [[
                    'question' => $data['interview']['question'],
                    'points' => $data['interview']['points'],
                ]]);
            }
        });
    }
}
