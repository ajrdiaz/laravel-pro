<?php

namespace Tests\Concerns;

use Illuminate\Support\Collection;

/**
 * Lets a test compare what the app shows with the content files it was seeded from,
 * instead of hard-coding how many modules, cards or resources there are.
 */
trait ReadsContentFiles
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function roadmap(): Collection
    {
        return $this->contentFile('roadmap');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function stacksFile(): Collection
    {
        return $this->contentFile('stacks');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function projectsFile(): Collection
    {
        return $this->contentFile('projects');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function resourcesFile(): Collection
    {
        return $this->contentFile('resources');
    }

    /**
     * How many items of a kind the roadmap holds in total, such as `flashcards`.
     */
    protected function roadmapTotal(string $key): int
    {
        return $this->roadmap()->sum(fn (array $module) => count($module[$key]));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function contentFile(string $name): Collection
    {
        return collect(json_decode(file_get_contents(database_path("data/{$name}.json")), true, flags: JSON_THROW_ON_ERROR));
    }
}
