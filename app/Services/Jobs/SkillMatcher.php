<?php

namespace App\Services\Jobs;

use Illuminate\Support\Str;

/**
 * Finds known skills in the text of a job posting, using the dictionary in
 * database/data/skills.json.
 *
 * Matching ignores case and accents and only accepts whole words, so "Java" is not
 * found inside "JavaScript". Longer aliases are matched first and then masked, so
 * "React Native" is counted as React Native and not also as React.
 */
class SkillMatcher
{
    /** @var list<array{alias: string, skill: int}>|null */
    private ?array $aliases = null;

    /** @var list<array{name: string, aliases: list<string>, module: string|null, note?: string, category?: string, hint?: string}>|null */
    private ?array $skills = null;

    public function __construct(private readonly string $dictionaryPath) {}

    /**
     * @return list<SkillMatch> The most mentioned first; ties keep the dictionary order.
     */
    public function match(string $text): array
    {
        $remaining = ' '.$this->normalize($text).' ';
        $counts = [];

        foreach ($this->aliases() as ['alias' => $alias, 'skill' => $skill]) {
            $pattern = '/(?<![a-z0-9])'.preg_quote($alias, '/').'(?![a-z0-9])/';

            $remaining = (string) preg_replace_callback($pattern, function (array $found) use (&$counts, $skill) {
                $counts[$skill] = ($counts[$skill] ?? 0) + 1;

                return str_repeat(' ', strlen($found[0]));
            }, $remaining);
        }

        // Counts were gathered longest alias first; restore the dictionary order so ties are predictable.
        ksort($counts);

        $matches = [];

        foreach ($counts as $index => $mentions) {
            $skill = $this->skills()[$index];

            $matches[] = new SkillMatch(
                name: $skill['name'],
                module: $skill['module'],
                mentions: $mentions,
                note: $skill['note'] ?? null,
                category: $skill['category'] ?? null,
                hint: $skill['hint'] ?? null,
            );
        }

        // usort is stable since PHP 8.0, so equal counts keep their order.
        usort($matches, fn (SkillMatch $a, SkillMatch $b) => $b->mentions <=> $a->mentions);

        return $matches;
    }

    /**
     * Lowercase ASCII with single spaces, so "Programación  ESTÁ" becomes "programacion esta".
     */
    private function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strtolower(Str::ascii($text))));
    }

    /**
     * @return list<array{alias: string, skill: int}> Longest alias first.
     */
    private function aliases(): array
    {
        if ($this->aliases !== null) {
            return $this->aliases;
        }

        $aliases = [];

        foreach ($this->skills() as $index => $skill) {
            foreach ($skill['aliases'] as $alias) {
                $aliases[] = ['alias' => $this->normalize($alias), 'skill' => $index];
            }
        }

        usort($aliases, fn (array $a, array $b) => strlen($b['alias']) <=> strlen($a['alias']));

        return $this->aliases = $aliases;
    }

    /**
     * @return list<array{name: string, aliases: list<string>, module: string|null, note?: string, category?: string, hint?: string}>
     */
    private function skills(): array
    {
        return $this->skills ??= json_decode(
            (string) file_get_contents($this->dictionaryPath),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
