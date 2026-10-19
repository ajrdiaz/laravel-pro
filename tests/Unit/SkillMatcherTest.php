<?php

namespace Tests\Unit;

use App\Services\Jobs\SkillMatch;
use App\Services\Jobs\SkillMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SkillMatcherTest extends TestCase
{
    private function matcher(): SkillMatcher
    {
        return new SkillMatcher(dirname(__DIR__, 2).'/database/data/skills.json');
    }

    /**
     * @return list<string>
     */
    private function names(string $text): array
    {
        return array_map(fn (SkillMatch $match) => $match->name, $this->matcher()->match($text));
    }

    public function test_it_ignores_case_and_accents()
    {
        $names = $this->names('Experiencia con LARAVEL y Programación en PhP, bases de datos RELACIONALES.');

        $this->assertContains('Laravel', $names);
        $this->assertContains('PHP', $names);
        $this->assertContains('Bases de datos relacionales', $names);
    }

    public function test_it_returns_nothing_for_text_without_known_skills()
    {
        $this->assertSame([], $this->matcher()->match('Buscamos una persona proactiva y con ganas de aprender.'));
        $this->assertSame([], $this->matcher()->match(''));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function lookAlikes(): array
    {
        return [
            'Java is not found inside JavaScript' => ['Dominio de JavaScript', 'Java'],
            'Git is not found inside digital or GitHub' => ['Transformación digital con GitHub', 'Git'],
            'ORM is not found inside platform' => ['Una platform de pagos', 'ORM'],
            'React is not found inside reaction' => ['Buscamos una reaction rápida', 'React'],
            'Pest is not found inside pestaña' => ['Abre la pestaña de ajustes', 'Pest'],
            'SQL is not found inside MySQL' => ['Experiencia con MySQL', 'SQL'],
            'API is not found inside capital' => ['Capital humano', 'APIs'],
        ];
    }

    #[DataProvider('lookAlikes')]
    public function test_it_only_matches_whole_words(string $text, string $mustNotAppear)
    {
        $this->assertNotContains($mustNotAppear, $this->names($text));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function longerAliasesWin(): array
    {
        return [
            'React Native is not also React' => ['Desarrollo con React Native', 'React Native', 'React'],
            'Next.js is not also JavaScript' => ['Experiencia con Next.js', 'Next.js', 'JavaScript'],
            'Node.js is not also JavaScript' => ['Backend en Node.js', 'Node.js', 'JavaScript'],
            'ASP.NET is only .NET' => ['Equipo de ASP.NET', '.NET', 'CSS'],
            'REST API is not also APIs' => ['Diseñar una REST API', 'API REST', 'APIs'],
            'GitHub Actions is not also GitHub' => ['Pipelines en GitHub Actions', 'GitHub Actions', 'GitHub'],
        ];
    }

    #[DataProvider('longerAliasesWin')]
    public function test_the_longest_alias_wins_and_is_not_counted_twice(string $text, string $expected, string $mustNotAppear)
    {
        $names = $this->names($text);

        $this->assertContains($expected, $names);
        $this->assertNotContains($mustNotAppear, $names);
    }

    public function test_it_counts_mentions_and_puts_the_most_mentioned_first()
    {
        $matches = $this->matcher()->match('Vue, Laravel, Docker. Se valora Laravel. Laravel es obligatorio.');

        $this->assertSame('Laravel', $matches[0]->name);
        $this->assertSame(3, $matches[0]->mentions);
        $this->assertSame(1, collect($matches)->firstWhere('name', 'Vue')->mentions);
    }

    public function test_equal_counts_keep_the_dictionary_order()
    {
        $names = $this->names('Docker, Vue, PHP');

        // The dictionary lists PHP before Vue before Docker.
        $this->assertSame(['PHP', 'Vue', 'Docker'], $names);
    }

    public function test_skills_outside_the_roadmap_carry_their_category_and_no_module()
    {
        $aws = collect($this->matcher()->match('Conocimientos de AWS y Kubernetes'))->keyBy('name');

        $this->assertNull($aws['AWS']->module);
        $this->assertSame('Nube', $aws['AWS']->category);
        $this->assertNotNull($aws['AWS']->hint);
        $this->assertNull($aws['Kubernetes']->module);
    }

    public function test_partial_matches_explain_what_the_module_does_not_cover()
    {
        $match = collect($this->matcher()->match('Usamos Elasticsearch'))->firstWhere('name', 'Elasticsearch');

        $this->assertSame('scout', $match->module);
        $this->assertNotNull($match->note);
    }

    public function test_a_realistic_posting()
    {
        $posting = <<<'TXT'
            Desarrollador/a Backend Laravel (remoto)

            Requisitos
            - 3+ años con PHP 8 y Laravel (Eloquent, colas, Horizon)
            - MySQL o PostgreSQL; Redis
            - APIs REST con Sanctum, tests con Pest o PHPUnit
            - Docker, Git y CI/CD (GitHub Actions)
            Deseable: Vue 3 o React, TypeScript, Tailwind. Inglés técnico. AWS.
            TXT;

        $names = $this->names($posting);

        foreach (['Laravel', 'PHP', 'Eloquent', 'Colas', 'Horizon', 'MySQL', 'PostgreSQL', 'Redis', 'API REST', 'Sanctum', 'Pest', 'PHPUnit', 'Docker', 'Git', 'CI/CD', 'GitHub Actions', 'Vue', 'React', 'TypeScript', 'Tailwind CSS', 'Inglés', 'AWS'] as $expected) {
            $this->assertContains($expected, $names, "Expected to find [{$expected}].");
        }

        $this->assertNotContains('GitHub', $names, 'GitHub Actions should not also count as GitHub.');
    }
}
