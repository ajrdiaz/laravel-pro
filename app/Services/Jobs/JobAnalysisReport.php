<?php

namespace App\Services\Jobs;

use App\Models\User;
use App\Services\ModuleProgress;
use App\Services\ProgressReport;

/**
 * Crosses the skills a job posting asks for with how far a user is on the roadmap.
 */
class JobAnalysisReport
{
    public function __construct(
        private readonly SkillMatcher $matcher,
        private readonly ProgressReport $progress,
    ) {}

    /**
     * @return array{
     *     summary: array{detected: int, covered: int, outside: int, readiness: int|null},
     *     modules: list<array{slug: string, title: string, section: string, command: string, status: string, mentions: int, progress: array{done: int, total: int, pct: int}, skills: list<array{name: string, mentions: int, note: string|null}>}>,
     *     outside: list<array{name: string, mentions: int, category: string|null, hint: string|null}>
     * }
     */
    public function for(User $user, string $text): array
    {
        $matches = $this->matcher->match($text);
        $modules = $this->progress->modules($user)->keyBy('slug');

        $requested = [];
        $outside = [];

        foreach ($matches as $match) {
            $module = $match->module === null ? null : $modules->get($match->module);

            if ($module === null) {
                $outside[] = [
                    'name' => $match->name,
                    'mentions' => $match->mentions,
                    'category' => $match->category,
                    'hint' => $match->hint,
                ];

                continue;
            }

            $requested[$module->slug] ??= ['module' => $module, 'skills' => []];
            $requested[$module->slug]['skills'][] = $match;
        }

        $rows = [];

        foreach ($requested as ['module' => $module, 'skills' => $skills]) {
            $rows[] = [
                'position' => $module->position,
                'module' => [
                    'slug' => $module->slug,
                    'title' => $module->title,
                    'section' => $module->section,
                    'command' => $module->command,
                    'status' => $this->status($module->percentage()),
                    'mentions' => array_sum(array_map(fn (SkillMatch $skill) => $skill->mentions, $skills)),
                    'progress' => $module->toArray()['progress'],
                    'skills' => array_map(fn (SkillMatch $skill) => [
                        'name' => $skill->name,
                        'mentions' => $skill->mentions,
                        'note' => $skill->note,
                    ], $skills),
                ],
            ];
        }

        // What the posting stresses most comes first; the roadmap order breaks ties.
        usort($rows, fn (array $a, array $b) => [$b['module']['mentions'], $a['position']] <=> [$a['module']['mentions'], $b['position']]);

        $report = array_map(fn (array $row) => $row['module'], $rows);

        return [
            'summary' => [
                'detected' => count($matches),
                'covered' => count($matches) - count($outside),
                'outside' => count($outside),
                'readiness' => $report === []
                    ? null
                    : (int) round(array_sum(array_map(fn (array $module) => $module['progress']['pct'], $report)) / count($report)),
            ],
            'modules' => $report,
            'outside' => $outside,
        ];
    }

    private function status(int $percentage): string
    {
        return match (true) {
            $percentage >= ModuleProgress::READY_PERCENTAGE => 'ready',
            $percentage > 0 => 'learning',
            default => 'todo',
        };
    }
}
