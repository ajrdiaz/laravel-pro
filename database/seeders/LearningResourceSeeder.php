<?php

namespace Database\Seeders;

use App\Enums\ResourceKind;
use App\Enums\ResourceLanguage;
use App\Models\LearningResource;
use App\Models\Module;
use Database\Seeders\Concerns\SyncsPositionedRows;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class LearningResourceSeeder extends Seeder
{
    use SyncsPositionedRows;

    /**
     * Sync the curated resources from database/data/resources.json.
     *
     * Resources are matched by position within their module, and modules missing from the
     * file end up with none. Run RoadmapSeeder first: resources belong to its modules.
     */
    public function run(): void
    {
        /** @var list<array{module: string, items: list<array<string, mixed>>}> $groups */
        $groups = json_decode(File::get(database_path('data/resources.json')), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($groups) {
            $seeded = [];

            foreach ($groups as $group) {
                $module = Module::where('slug', $group['module'])->first()
                    ?? throw new InvalidArgumentException("Unknown module [{$group['module']}] in the resources file.");

                $this->syncRows($module->learningResources(), array_map(
                    fn (array $item) => [
                        'title' => $item['title'],
                        'url' => $item['url'],
                        'provider' => $item['provider'],
                        'kind' => ResourceKind::from($item['kind']),
                        'language' => ResourceLanguage::from($item['language']),
                        'is_free' => $item['is_free'],
                        'note' => $item['note'],
                    ],
                    $group['items'],
                ));

                $seeded[] = $module->id;
            }

            LearningResource::whereNotIn('module_id', $seeded)->delete();
        });
    }
}
