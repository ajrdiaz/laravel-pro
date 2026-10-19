<?php

namespace Tests\Feature\Study;

use App\Enums\StackCategory;
use App\Models\Completion;
use App\Models\Concept;
use App\Models\Module;
use App\Models\Stack;
use App\Models\StackTechnology;
use App\Models\User;
use Database\Seeders\RoadmapSeeder;
use Database\Seeders\StackSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\Concerns\ReadsContentFiles;
use Tests\TestCase;

class StackTest extends TestCase
{
    use ReadsContentFiles;
    use RefreshDatabase;

    private function seedCatalogue(): void
    {
        $this->seed([RoadmapSeeder::class, StackSeeder::class]);
    }

    public function test_it_loads_the_whole_catalogue()
    {
        $this->seedCatalogue();

        $this->assertSame(11, Stack::count());
        $this->assertSame(
            collect(StackCategory::cases())->map->value->sort()->values()->all(),
            Stack::pluck('category')->map->value->unique()->sort()->values()->all(),
            'Every category should have at least one stack.',
        );
    }

    public function test_every_stack_is_complete_enough_to_be_useful()
    {
        $this->seedCatalogue();

        Stack::with(['technologies', 'modules'])->get()->each(function (Stack $stack) {
            $label = "Stack [{$stack->slug}]";

            $this->assertNotEmpty($stack->headline, "$label has no headline.");
            $this->assertNotEmpty($stack->when_to_use, "$label has no reasons to pick it.");
            $this->assertNotEmpty($stack->when_to_avoid, "$label has no reasons to avoid it.");
            $this->assertNotEmpty($stack->pros, "$label has no pros.");
            $this->assertNotEmpty($stack->cons, "$label has no cons.");
            $this->assertIsList($stack->also_learn, "$label should list leftover topics, possibly none.");
            $this->assertGreaterThanOrEqual(5, $stack->technologies->count(), "$label describes too few technologies.");
            $this->assertNotEmpty($stack->modules, "$label requires no roadmap module.");
            $this->assertNotEmpty($stack->sources, "$label cites no source.");

            foreach ($stack->sources as $source) {
                $this->assertStringStartsWith('https://', $source['url'], "$label has a non-https source.");
            }
        });
    }

    public function test_running_it_again_updates_in_place_without_duplicating_or_changing_ids()
    {
        $this->seedCatalogue();

        $ids = StackTechnology::orderBy('id')->pluck('id');
        $links = DB::table('module_stack')->count();
        Stack::query()->update(['summary' => 'stale']);

        $this->seed(StackSeeder::class);

        $this->assertSame(11, Stack::count());
        $this->assertEquals($ids, StackTechnology::orderBy('id')->pluck('id'));
        $this->assertSame($links, DB::table('module_stack')->count());
        $this->assertSame(0, Stack::where('summary', 'stale')->count());
    }

    public function test_stacks_that_left_the_file_are_removed_with_their_technologies()
    {
        $this->seedCatalogue();

        $obsolete = Stack::factory()->create();
        $obsolete->technologies()->create(['position' => 1, 'layer' => 'backend', 'name' => 'Old', 'note' => 'Old.']);
        $obsolete->modules()->attach(Module::firstOrFail());

        $this->seed(StackSeeder::class);

        $this->assertSame(11, Stack::count());
        $this->assertDatabaseMissing('stack_technologies', ['stack_id' => $obsolete->id]);
        $this->assertDatabaseMissing('module_stack', ['stack_id' => $obsolete->id]);
    }

    public function test_a_stack_pointing_at_a_missing_module_fails_loudly()
    {
        $this->seed(RoadmapSeeder::class);
        Module::where('slug', 'ine')->delete();

        try {
            $this->seed(StackSeeder::class);
            $this->fail('The seeder should have rejected the unknown module.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Unknown module [ine]', $exception->getMessage());
        }

        $this->assertSame(0, Stack::count(), 'A failed run should not leave half the catalogue behind.');
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->seedCatalogue();

        $this->get(route('stacks.index'))->assertRedirect(route('login'));
        $this->get(route('stacks.show', 'rest-api'))->assertRedirect(route('login'));
    }

    public function test_the_index_lists_every_stack_with_the_categories_to_filter_by()
    {
        $this->seedCatalogue();

        $this->actingAs(User::factory()->create())->get(route('stacks.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stacks')
                ->has('categories', 4)
                ->where('categories.0', ['value' => 'web', 'label' => 'Aplicaciones web'])
                ->has('stacks', 11)
                ->has('stacks.0', fn (Assert $stack) => $stack
                    ->where('slug', 'monolito-blade')
                    ->where('category', 'web')
                    ->hasAll(['name', 'summary', 'headline', 'reviewed_on'])));
    }

    public function test_a_stack_page_groups_technologies_by_layer_in_a_fixed_order()
    {
        $this->seedCatalogue();

        $this->actingAs(User::factory()->create())->get(route('stacks.show', 'tiempo-real'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('StackShow')
                ->where('stack.slug', 'tiempo-real')
                ->where('stack.category', ['value' => 'async', 'label' => 'Segundo plano y tiempo real'])
                ->where('layers.0', fn ($group) => $group['layer'] === 'backend' && $group['label'] === 'Backend')
                ->where('layers', fn ($layers) => collect($layers)->pluck('layer')->all() === [
                    'backend', 'auth', 'queue', 'cache', 'realtime', 'testing', 'deploy', 'monitoring',
                ]));
    }

    public function test_a_stack_page_shows_the_users_progress_in_each_required_module()
    {
        $this->seedCatalogue();
        $user = User::factory()->create();

        $inertia = Module::where('slug', 'ine')->firstOrFail();
        $concept = Concept::where('module_id', $inertia->id)->orderBy('position')->firstOrFail();
        Completion::create(['user_id' => $user->id, 'completable_type' => 'concept', 'completable_id' => $concept->id]);

        // The modules the stack lists, in the order they appear in the roadmap.
        $position = $this->roadmap()->pluck('position', 'slug');
        $expected = collect($this->stacksFile()->firstWhere('slug', 'inertia-vue-react')['modules'])
            ->sortBy(fn (string $slug) => $position[$slug])
            ->values();

        $this->actingAs($user)->get(route('stacks.show', 'inertia-vue-react'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('modules', $expected->count())
                ->where('modules', fn ($modules) => collect($modules)->pluck('slug')->all() === $expected->all())
                ->where('modules', fn ($modules) => collect($modules)->firstWhere('slug', 'ine')['progress']['done'] === 1)
                ->where('modules', fn ($modules) => collect($modules)->where('slug', '!=', 'ine')->every(fn ($m) => $m['progress']['done'] === 0))
                ->has('stats'));
    }

    public function test_a_stack_with_nothing_left_to_learn_has_no_leftover_topics()
    {
        $this->seedCatalogue();

        $this->actingAs(User::factory()->create())->get(route('stacks.show', 'tiempo-real'))
            ->assertInertia(fn (Assert $page) => $page->where('stack.also_learn', []));
    }

    public function test_an_unknown_stack_is_not_found()
    {
        $this->seedCatalogue();

        $this->actingAs(User::factory()->create())->get(route('stacks.show', 'nope'))->assertNotFound();
    }
}
