<?php

namespace Tests\Feature\Study;

use App\Services\Jobs\SkillMatcher;
use Illuminate\Support\Str;
use Tests\Concerns\ReadsContentFiles;
use Tests\TestCase;

/**
 * Guards the skill dictionary the job analyzer relies on.
 */
class SkillDictionaryTest extends TestCase
{
    use ReadsContentFiles;

    private function skills()
    {
        return collect(json_decode(file_get_contents(database_path('data/skills.json')), true, flags: JSON_THROW_ON_ERROR));
    }

    private function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strtolower(Str::ascii($text))));
    }

    public function test_every_entry_is_well_formed()
    {
        $this->skills()->each(function (array $skill) {
            $this->assertNotSame('', trim($skill['name']));
            $this->assertNotEmpty($skill['aliases'], "[{$skill['name']}] has no aliases.");
            $this->assertArrayHasKey('module', $skill, "[{$skill['name']}] must say which module teaches it, or null.");

            if ($skill['module'] === null) {
                $this->assertNotEmpty($skill['category'] ?? null, "[{$skill['name']}] is outside the roadmap, so it needs a category.");
            }
        });
    }

    public function test_every_module_a_skill_points_at_exists()
    {
        $modules = $this->roadmap()->pluck('slug')->all();

        $this->skills()->whereNotNull('module')->each(function (array $skill) use ($modules) {
            $this->assertContains($skill['module'], $modules, "[{$skill['name']}] points at unknown module [{$skill['module']}].");
        });
    }

    public function test_every_module_can_be_recommended_by_at_least_one_skill()
    {
        $covered = $this->skills()->pluck('module')->filter()->unique()->all();

        foreach ($this->roadmap()->pluck('slug') as $slug) {
            $this->assertContains($slug, $covered, "No skill leads to module [{$slug}], so the analyzer could never recommend it.");
        }
    }

    public function test_aliases_are_unique_once_normalized()
    {
        $aliases = $this->skills()->flatMap(fn (array $skill) => array_map(fn (string $alias) => $this->normalize($alias), $skill['aliases']));
        $repeated = $aliases->duplicates()->unique()->values()->all();

        $this->assertSame([], $repeated, 'These aliases belong to two skills, so matching would be ambiguous.');
    }

    public function test_every_skill_name_is_unique()
    {
        $names = $this->skills()->pluck('name');

        $this->assertSame($names->count(), $names->unique()->count());
    }

    public function test_every_alias_is_found_by_the_matcher()
    {
        $matcher = new SkillMatcher(database_path('data/skills.json'));

        $this->skills()->each(function (array $skill) use ($matcher) {
            foreach ($skill['aliases'] as $alias) {
                $found = collect($matcher->match("Requisito: {$alias}."))->pluck('name')->all();

                // A longer alias of another skill may swallow this one ("react native" masks "react");
                // an alias must at least be recognised as some skill when it stands alone.
                $this->assertNotEmpty($found, "Alias [{$alias}] of [{$skill['name']}] is not matched on its own.");
                $this->assertContains($skill['name'], $found, "Alias [{$alias}] resolves to {$found[0]} instead of [{$skill['name']}].");
            }
        });
    }
}
