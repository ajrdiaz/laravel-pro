<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class LessonSeeder extends Seeder
{
    private ?string $directory = null;

    /**
     * Read the lessons from another directory instead of database/data/lessons.
     */
    public function from(string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    /**
     * Sync the lessons from Markdown files laid out as `<module-slug>/<NN>-<lesson-slug>.md`.
     *
     * A lesson is identified by its module and slug, so reordering or retitling keeps its
     * id and the progress users have made on it. Lessons whose file is gone are deleted.
     * Run RoadmapSeeder first: every directory name must be an existing module slug.
     */
    public function run(): void
    {
        $directory = $this->directory ?? database_path('data/lessons');
        $moduleIds = Module::pluck('id', 'slug');

        DB::transaction(function () use ($directory, $moduleIds) {
            /** @var array<int, list<string>> $kept */
            $kept = [];

            foreach (File::directories($directory) as $moduleDirectory) {
                $moduleSlug = basename($moduleDirectory);
                $moduleId = $moduleIds[$moduleSlug]
                    ?? throw new InvalidArgumentException("Unknown module [{$moduleSlug}] in the lessons directory.");

                $files = collect(File::files($moduleDirectory))
                    ->filter(fn ($file) => $file->getExtension() === 'md')
                    ->sortBy(fn ($file) => $file->getFilename())
                    ->values();

                foreach ($files as $index => $file) {
                    $slug = preg_replace('/^\d+-/', '', $file->getFilenameWithoutExtension());

                    if (in_array($slug, $kept[$moduleId] ?? [], true)) {
                        throw new InvalidArgumentException("Module [{$moduleSlug}] has two lessons with the slug [{$slug}].");
                    }

                    ['meta' => $meta, 'body' => $body] = $this->parse($file->getPathname());

                    Lesson::updateOrCreate(
                        ['module_id' => $moduleId, 'slug' => $slug],
                        [
                            'position' => $index + 1,
                            'title' => $meta['title'],
                            'summary' => $meta['summary'],
                            'minutes' => $meta['minutes'],
                            'body' => $body,
                        ],
                    );

                    $kept[$moduleId][] = $slug;
                }
            }

            foreach ($moduleIds as $moduleId) {
                Lesson::where('module_id', $moduleId)
                    ->whereNotIn('slug', $kept[$moduleId] ?? [])
                    ->delete();
            }
        });
    }

    /**
     * Split a lesson file into its front matter and its Markdown body.
     *
     * The front matter sits between two `---` lines and holds `key: value` pairs.
     *
     * @return array{meta: array{title: string, summary: string, minutes: int}, body: string}
     */
    private function parse(string $path): array
    {
        $contents = str_replace("\r\n", "\n", File::get($path));

        if (! preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $contents, $matches)) {
            throw new InvalidArgumentException("Lesson [{$path}] has no front matter between --- lines.");
        }

        $values = [];

        foreach (explode("\n", $matches[1]) as $line) {
            if (trim($line) === '') {
                continue;
            }

            [$key, $value] = array_pad(explode(':', $line, 2), 2, '');
            $values[trim($key)] = trim($value);
        }

        foreach (['title', 'summary', 'minutes'] as $required) {
            if (($values[$required] ?? '') === '') {
                throw new InvalidArgumentException("Lesson [{$path}] is missing [{$required}] in its front matter.");
            }
        }

        if (! ctype_digit($values['minutes']) || (int) $values['minutes'] < 1) {
            throw new InvalidArgumentException("Lesson [{$path}] needs a positive whole number for [minutes].");
        }

        return [
            'meta' => [
                'title' => $values['title'],
                'summary' => $values['summary'],
                'minutes' => (int) $values['minutes'],
            ],
            'body' => trim($matches[2]),
        ];
    }
}
