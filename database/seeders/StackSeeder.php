<?php

namespace Database\Seeders;

use App\Enums\StackCategory;
use App\Enums\StackLayer;
use App\Models\Module;
use App\Models\Stack;
use Database\Seeders\Concerns\SyncsPositionedRows;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class StackSeeder extends Seeder
{
    use SyncsPositionedRows;

    /**
     * Sync the stack catalogue from database/data/stacks.json.
     *
     * Stacks are matched by slug and their technologies by position. Stacks that are no
     * longer in the file are removed. Run RoadmapSeeder first: stacks link to its modules.
     */
    public function run(): void
    {
        /** @var list<array<string, mixed>> $stacks */
        $stacks = json_decode(File::get(database_path('data/stacks.json')), true, flags: JSON_THROW_ON_ERROR);

        $moduleIds = Module::pluck('id', 'slug');

        DB::transaction(function () use ($stacks, $moduleIds) {
            foreach ($stacks as $index => $data) {
                $stack = Stack::updateOrCreate(
                    ['slug' => $data['slug']],
                    [
                        'position' => $index + 1,
                        'category' => StackCategory::from($data['category']),
                        'name' => $data['name'],
                        'summary' => $data['summary'],
                        'headline' => $data['headline'],
                        'when_to_use' => $data['when_to_use'],
                        'when_to_avoid' => $data['when_to_avoid'],
                        'pros' => $data['pros'],
                        'cons' => $data['cons'],
                        'also_learn' => $data['also_learn'],
                        'sources' => $data['sources'],
                        'reviewed_on' => $data['reviewed_on'],
                    ],
                );

                $this->syncRows($stack->technologies(), array_values(array_map(
                    fn (array $technology) => [
                        'layer' => StackLayer::from($technology['layer']),
                        'name' => $technology['name'],
                        'note' => $technology['note'],
                    ],
                    $data['technologies'],
                )));

                $stack->modules()->sync(array_map(
                    fn (string $slug) => $moduleIds[$slug]
                        ?? throw new InvalidArgumentException("Unknown module [{$slug}] in stack [{$data['slug']}]."),
                    $data['modules'],
                ));
            }

            Stack::whereNotIn('slug', array_column($stacks, 'slug'))->delete();
        });
    }
}
