<?php

namespace Tests\Feature\Study;

use App\Models\Stack;
use App\Models\User;
use Database\Seeders\RoadmapSeeder;
use Database\Seeders\StackSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CompletesModules;
use Tests\Concerns\ReadsContentFiles;
use Tests\TestCase;

class StackCompareTest extends TestCase
{
    use CompletesModules;
    use ReadsContentFiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoadmapSeeder::class, StackSeeder::class]);
    }

    /**
     * @param  array<int, mixed>|string  $stacks
     */
    private function compare(array|string $stacks, ?User $user = null)
    {
        return $this->actingAs($user ?? User::factory()->create())
            ->get(route('stacks.compare', $stacks === [] ? [] : ['stacks' => $stacks]));
    }

    /**
     * @return list<string>
     */
    private function modulesOf(string $stack): array
    {
        return $this->stacksFile()->firstWhere('slug', $stack)['modules'];
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get(route('stacks.compare'))->assertRedirect(route('login'));
    }

    public function test_the_comparison_is_not_mistaken_for_a_stack_called_compare()
    {
        $this->assertSame('/stacks/compare', route('stacks.compare', absolute: false));

        $this->compare([])->assertInertia(fn (Assert $page) => $page->component('StackCompare'));
    }

    public function test_it_offers_every_stack_to_pick_from_and_shows_nothing_until_two_are_chosen()
    {
        $first = Stack::orderBy('position')->firstOrFail();

        $this->compare([])->assertInertia(fn (Assert $page) => $page
            ->has('options', Stack::count())
            ->where('options.0', ['slug' => $first->slug, 'name' => $first->name])
            ->where('selected', [])
            ->where('columns', [])
            ->where('layers', []));

        $this->compare(['rest-api'])->assertInertia(fn (Assert $page) => $page
            ->where('selected', ['rest-api'])
            ->where('columns', [])
            ->where('layers', []));
    }

    public function test_it_lines_up_two_stacks_keeping_the_order_they_were_chosen_in()
    {
        $this->compare(['rest-api', 'inertia-vue-react'])->assertInertia(fn (Assert $page) => $page
            ->where('selected', ['rest-api', 'inertia-vue-react'])
            ->has('columns', 2)
            ->has('columns.0', fn (Assert $column) => $column
                ->where('slug', 'rest-api')
                ->where('category', 'APIs y frontends desacoplados')
                ->hasAll(['name', 'summary', 'headline', 'when_to_use', 'when_to_avoid', 'pros', 'cons', 'readiness', 'modules', 'layers']))
            ->where('columns.1.slug', 'inertia-vue-react'));

        $this->compare(['inertia-vue-react', 'rest-api'])->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.slug', 'inertia-vue-react')
            ->where('columns.1.slug', 'rest-api'));
    }

    public function test_it_compares_at_most_three_and_ignores_unknown_and_repeated_stacks()
    {
        $this->compare(['rest-api', 'rest-api', 'nope', 'tiempo-real', 'colas-y-workers', 'event-driven'])
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected', ['rest-api', 'tiempo-real', 'colas-y-workers'])
                ->has('columns', 3));
    }

    public function test_a_choice_that_loses_a_stack_to_an_unknown_slug_falls_back_to_the_empty_state()
    {
        $this->compare(['rest-api', 'nope'])->assertInertia(fn (Assert $page) => $page
            ->where('selected', ['rest-api'])
            ->where('columns', []));
    }

    public function test_choices_that_are_not_plain_slugs_are_ignored()
    {
        $this->compare([['rest-api'], 'rest-api', 'tiempo-real'])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('selected', ['rest-api', 'tiempo-real']));

        // A single value instead of a list is still understood.
        $this->compare('rest-api')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('selected', ['rest-api']));
    }

    public function test_the_layers_are_exactly_those_some_chosen_stack_uses_in_the_fixed_display_order()
    {
        $response = $this->compare(['monolito-blade', 'alto-rendimiento-octane', 'tiempo-real']);

        $columns = collect($response->inertiaProps('columns'));
        $layers = collect($response->inertiaProps('layers'));

        $used = $columns->flatMap(fn (array $column) => array_keys($column['layers']))->unique();

        $this->assertNotEmpty($used);
        $this->assertSame(
            $used->sort()->values()->all(),
            $layers->pluck('layer')->sort()->values()->all(),
            'Rows should exist for the layers in use, and only those.',
        );
        $this->assertSame('backend', $layers->first()['layer'], 'Backend is the first layer in the display order.');
        $this->assertSame('Backend', $layers->first()['label']);
        $this->assertSame($layers->pluck('layer')->all(), $layers->pluck('layer')->unique()->all());

        // A stack that doesn't use a layer simply has no entry for it, which the table shows as a dash.
        $columns->each(fn (array $column) => collect($column['layers'])->each(fn (array $names) => $this->assertNotEmpty($names)));
    }

    public function test_a_column_lists_the_technology_names_of_each_layer()
    {
        $expected = Stack::where('slug', 'tiempo-real')->firstOrFail()->technologies()->where('layer', 'realtime')->pluck('name')->all();

        $this->assertNotEmpty($expected);

        $this->compare(['rest-api', 'tiempo-real'])->assertInertia(fn (Assert $page) => $page
            ->where('columns.1.layers.realtime', $expected)
            ->missing('columns.0.layers.realtime'));
    }

    public function test_the_readiness_of_each_column_follows_the_users_progress()
    {
        $user = User::factory()->create();
        $restApi = $this->modulesOf('rest-api');
        $inertia = $this->modulesOf('inertia-vue-react');
        $shared = array_values(array_intersect($restApi, $inertia));
        $onlyInertia = array_values(array_diff($inertia, $restApi));

        $this->assertNotEmpty($onlyInertia, 'The stacks need a module in common and one that is not for this test to mean anything.');

        $this->completeModule($user, $onlyInertia[0]);

        $this->compare(['rest-api', 'inertia-vue-react'], $user)->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.readiness', 0)
            ->where('columns.0.modules', count($restApi))
            ->where('columns.1.readiness', (int) round(100 / count($inertia)))
            ->where('columns.1.modules', count($inertia)));

        // A module both stacks need counts in each of them.
        $this->completeModule($user, $shared[0]);

        $this->compare(['rest-api', 'inertia-vue-react'], $user)->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.readiness', (int) round(100 / count($restApi)))
            ->where('columns.1.readiness', (int) round(200 / count($inertia))));
    }

    public function test_one_users_progress_does_not_leak_into_another_comparison()
    {
        $other = User::factory()->create();

        foreach ($this->modulesOf('inertia-vue-react') as $slug) {
            $this->completeModule($other, $slug);
        }

        $this->compare(['rest-api', 'inertia-vue-react'])->assertInertia(fn (Assert $page) => $page
            ->where('columns.1.readiness', 0));
    }
}
