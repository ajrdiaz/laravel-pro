<?php

namespace Database\Seeders;

use App\Enums\ProjectLevel;
use App\Models\Module;
use App\Models\Project;
use App\Models\Stack;
use Database\Seeders\Concerns\SyncsPositionedRows;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class ProjectSeeder extends Seeder
{
    use SyncsPositionedRows;

    /**
     * Sync the portfolio projects from database/data/projects.json.
     *
     * Projects are matched by slug and their milestones by position, so ticks the user has
     * made survive an edit to the wording. Projects that are no longer in the file are
     * removed. Run RoadmapSeeder and StackSeeder first: projects link to their modules and stack.
     */
    public function run(): void
    {
        /** @var list<array<string, mixed>> $projects */
        $projects = json_decode(File::get(database_path('data/projects.json')), true, flags: JSON_THROW_ON_ERROR);

        $moduleIds = Module::pluck('id', 'slug');
        $stackIds = Stack::pluck('id', 'slug');

        DB::transaction(function () use ($projects, $moduleIds, $stackIds) {
            foreach ($projects as $index => $data) {
                $project = Project::updateOrCreate(
                    ['slug' => $data['slug']],
                    [
                        'position' => $index + 1,
                        'level' => ProjectLevel::from($data['level']),
                        'title' => $data['title'],
                        'summary' => $data['summary'],
                        'hours' => $data['hours'],
                        'stack_id' => $data['stack'] === null
                            ? null
                            : ($stackIds[$data['stack']] ?? throw new InvalidArgumentException("Unknown stack [{$data['stack']}] in project [{$data['slug']}].")),
                        'deliverables' => $data['deliverables'],
                        'stretch' => $data['stretch'],
                    ],
                );

                $this->syncRows($project->milestones(), array_values(array_map(
                    fn (array $milestone) => ['title' => $milestone['title'], 'detail' => $milestone['detail']],
                    $data['milestones'],
                )));

                $project->modules()->sync(array_map(
                    fn (string $slug) => $moduleIds[$slug]
                        ?? throw new InvalidArgumentException("Unknown module [{$slug}] in project [{$data['slug']}]."),
                    $data['modules'],
                ));
            }

            Project::whereNotIn('slug', array_column($projects, 'slug'))->delete();
        });
    }
}
