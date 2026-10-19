<?php

namespace Tests\Feature\Study;

use Tests\Concerns\ReadsContentFiles;
use Tests\TestCase;

/**
 * Guards the quality of the content files themselves, not the code that loads them.
 * They only read files, so they are fast and never need seeding.
 */
class RoadmapContentTest extends TestCase
{
    use ReadsContentFiles;

    public function test_modules_are_numbered_from_one_without_gaps()
    {
        $this->assertSame(
            range(1, $this->roadmap()->count()),
            $this->roadmap()->pluck('position')->all(),
        );
    }

    public function test_slugs_and_titles_are_unique()
    {
        $slugs = $this->roadmap()->pluck('slug');
        $titles = $this->roadmap()->pluck('title');

        $this->assertSame($slugs->count(), $slugs->unique()->count(), 'Two modules share a slug.');
        $this->assertSame($titles->count(), $titles->unique()->count(), 'Two modules share a title.');
    }

    public function test_each_section_is_one_unbroken_run_of_modules()
    {
        $sections = $this->roadmap()->pluck('section');

        $this->assertNotContains('', $sections->all(), 'Every module needs a section.');

        // Collapse consecutive repeats: a section that comes back later would appear twice.
        $runs = $sections->reduce(
            fn (array $runs, string $section) => end($runs) === $section ? $runs : [...$runs, $section],
            [],
        );

        $this->assertSame(count($runs), count(array_unique($runs)), 'A section is split in two.');
    }

    public function test_commands_are_complete_command_lines()
    {
        $this->roadmap()->each(function (array $module) {
            $this->assertMatchesRegularExpression(
                '/^(php|npm|npx) \S/',
                $module['command'],
                "Module [{$module['slug']}] should show a full command, such as `php artisan route:list`.",
            );
        });
    }

    public function test_every_module_has_enough_to_study_and_practise()
    {
        $this->roadmap()->each(function (array $module) {
            $label = "Module [{$module['slug']}]";

            $this->assertGreaterThanOrEqual(5, count($module['concepts']), "$label has too few concepts.");
            $this->assertGreaterThanOrEqual(4, count($module['flashcards']), "$label has too few flashcards.");
            $this->assertGreaterThanOrEqual(2, count($module['quiz']), "$label has too few quiz questions.");
            $this->assertNotSame('', trim($module['challenge']), "$label has no challenge.");
            $this->assertNotSame('', trim($module['interview']['question']), "$label has no interview question.");
            $this->assertGreaterThanOrEqual(4, count($module['interview']['points']), "$label has too few interview points.");
        });
    }

    public function test_quiz_questions_are_well_formed()
    {
        $this->roadmap()->each(function (array $module) {
            foreach ($module['quiz'] as $question) {
                $label = "Module [{$module['slug']}], quiz «{$question['question']}»";

                $this->assertCount(4, $question['options'], "$label should have four options.");
                $this->assertSame(
                    count($question['options']),
                    count(array_unique($question['options'])),
                    "$label repeats an option.",
                );
                $this->assertArrayHasKey($question['correct_index'], $question['options'], $label);
                $this->assertNotSame('', trim($question['explanation']), "$label has no explanation.");
            }
        });
    }

    public function test_every_stack_only_lists_modules_that_exist()
    {
        $slugs = $this->roadmap()->pluck('slug');

        $this->stacksFile()->each(function (array $stack) use ($slugs) {
            foreach ($stack['modules'] as $module) {
                $this->assertContains($module, $slugs->all(), "Stack [{$stack['slug']}] lists unknown module [{$module}].");
            }

            $this->assertSame(
                count($stack['modules']),
                count(array_unique($stack['modules'])),
                "Stack [{$stack['slug']}] lists a module twice.",
            );
        });
    }

    public function test_every_resource_group_belongs_to_a_module_that_exists()
    {
        $slugs = $this->roadmap()->pluck('slug');

        $this->resourcesFile()->each(function (array $group) use ($slugs) {
            $this->assertContains($group['module'], $slugs->all(), "Resources for unknown module [{$group['module']}].");
        });

        $this->assertEqualsCanonicalizing(
            $slugs->all(),
            $this->resourcesFile()->pluck('module')->all(),
            'Every module should have resources, and none should be listed twice.',
        );
    }
}
