<?php

namespace Tests\Feature\Study;

use App\Models\User;
use Database\Seeders\RoadmapSeeder;
use Database\Seeders\StackSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CompletesModules;
use Tests\Concerns\ReadsContentFiles;
use Tests\TestCase;

class StackWizardTest extends TestCase
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
     * The modules some stacks need, in roadmap order and without repeats.
     *
     * @param  list<string>  $stacks
     * @return list<string>
     */
    private function modulesNeededBy(array $stacks): array
    {
        $position = $this->roadmap()->pluck('position', 'slug');

        return collect($stacks)
            ->flatMap(fn (string $slug) => $this->stacksFile()->firstWhere('slug', $slug)['modules'])
            ->unique()
            ->sortBy(fn (string $module) => $position[$module])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function wizard(array $query = [], ?User $user = null)
    {
        return $this->actingAs($user ?? User::factory()->create())->get(route('stacks.wizard', $query));
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get(route('stacks.wizard'))->assertRedirect(route('login'));
    }

    public function test_the_wizard_is_not_mistaken_for_a_stack_called_wizard()
    {
        $this->assertSame('/stacks/wizard', route('stacks.wizard', absolute: false));

        $this->wizard()->assertInertia(fn (Assert $page) => $page->component('StackWizard'));
    }

    public function test_it_asks_the_questions_and_shows_no_result_before_they_are_answered()
    {
        $this->wizard()->assertInertia(fn (Assert $page) => $page
            ->component('StackWizard')
            ->has('questions', 4)
            ->where('questions.0.key', 'clients')
            ->has('questions.0.options', 3)
            ->where('questions.3.multiple', true)
            ->where('answers', ['clients' => null, 'team' => null, 'ui' => null, 'extras' => []])
            ->where('result', null));
    }

    public function test_a_half_filled_form_is_shown_again_without_a_result()
    {
        $this->wizard(['clients' => 'web', 'team' => 'solo'])->assertInertia(fn (Assert $page) => $page
            ->where('answers', ['clients' => 'web', 'team' => 'solo', 'ui' => null, 'extras' => []])
            ->where('result', null));
    }

    public function test_tampered_answers_are_dropped_instead_of_failing()
    {
        $this->wizard(['clients' => 'satellite', 'team' => ['solo'], 'ui' => 'rich', 'extras' => ['bitcoin', 'search']])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('answers', ['clients' => null, 'team' => null, 'ui' => 'rich', 'extras' => ['search']])
                ->where('result', null));
    }

    public function test_it_recommends_a_base_stack_and_add_ons_with_the_reason_for_each()
    {
        $this->wizard(['clients' => 'web', 'team' => 'solo', 'ui' => 'rich', 'extras' => ['realtime']])
            ->assertInertia(fn (Assert $page) => $page
                ->where('answers.extras', ['realtime'])
                ->has('result.base', 1)
                ->has('result.base.0', fn (Assert $card) => $card
                    ->where('slug', 'inertia-vue-react')
                    ->where('category', 'Aplicaciones web')
                    ->whereType('why', 'string')
                    ->hasAll(['name', 'summary', 'headline']))
                ->has('result.addons', 2)
                ->where('result.addons.0.slug', 'tiempo-real')
                ->where('result.addons.1.slug', 'colas-y-workers')
                ->where('result.addons.1.why', fn (string $why) => $why !== ''));
    }

    public function test_an_app_that_also_serves_mobile_lists_the_api_first()
    {
        $this->wizard(['clients' => 'both', 'team' => 'solo', 'ui' => 'content'])
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.base', fn ($base) => collect($base)->pluck('slug')->all() === ['rest-api', 'monolito-blade'])
                ->where('result.addons', []));
    }

    public function test_the_preparation_starts_with_the_modules_furthest_from_done()
    {
        $needed = $this->modulesNeededBy(['inertia-vue-react']);

        $this->wizard(['clients' => 'web', 'team' => 'solo', 'ui' => 'rich'])
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.preparation.readiness', 0)
                ->where('result.preparation.modules', count($needed))
                ->has('result.preparation.startWith', 5)
                // Nothing is done, so ties break by roadmap order: the first five modules the stack needs.
                ->where('result.preparation.startWith', fn ($items) => collect($items)->pluck('slug')->all() === array_slice($needed, 0, 5))
                ->where('result.preparation.startWith.0.pct', 0));
    }

    public function test_the_preparation_follows_the_users_progress()
    {
        $user = User::factory()->create();
        $needed = $this->modulesNeededBy(['inertia-vue-react']);

        $this->completeModule($user, $needed[0]);

        $this->wizard(['clients' => 'web', 'team' => 'solo', 'ui' => 'rich'], $user)
            ->assertInertia(fn (Assert $page) => $page
                // One module complete out of however many the stack needs.
                ->where('result.preparation.readiness', (int) round(100 / count($needed)))
                // A finished module no longer needs starting, so the next ones take its place.
                ->where('result.preparation.startWith', fn ($items) => collect($items)->pluck('slug')->all() === array_slice($needed, 1, 5)));
    }

    public function test_a_user_who_knows_everything_the_stack_needs_has_nothing_to_start_with()
    {
        $user = User::factory()->create();

        foreach ($this->modulesNeededBy(['monolito-blade']) as $slug) {
            $this->completeModule($user, $slug);
        }

        $this->wizard(['clients' => 'web', 'team' => 'solo', 'ui' => 'content'], $user)
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.preparation.readiness', 100)
                ->where('result.preparation.startWith', []));
    }

    public function test_the_preparation_counts_a_module_once_even_when_several_stacks_need_it()
    {
        $union = $this->modulesNeededBy(['rest-api', 'inertia-vue-react']);
        $sum = count($this->stacksFile()->firstWhere('slug', 'rest-api')['modules'])
            + count($this->stacksFile()->firstWhere('slug', 'inertia-vue-react')['modules']);

        $this->assertLessThan($sum, count($union), 'The two stacks should share modules for this test to mean anything.');

        $this->wizard(['clients' => 'both', 'team' => 'solo', 'ui' => 'rich'])
            ->assertInertia(fn (Assert $page) => $page->where('result.preparation.modules', count($union)));
    }
}
