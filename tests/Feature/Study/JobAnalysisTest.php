<?php

namespace Tests\Feature\Study;

use App\Models\Completion;
use App\Models\FlashcardReview;
use App\Models\JobAnalysis;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RoadmapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JobAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private const string POSTING = 'Buscamos backend con Laravel y Vue. Se pide MySQL, Docker y APIs REST. Deseable: AWS y Kubernetes.';

    /**
     * Complete everything in a module so it counts as fully learned.
     */
    private function learnModule(User $user, string $slug): void
    {
        $module = Module::where('slug', $slug)->with(['concepts', 'flashcards', 'quizQuestions', 'challenges', 'interviewQuestions'])->firstOrFail();

        $complete = fn (string $type, $items) => $items->each(fn ($item) => Completion::create([
            'user_id' => $user->id,
            'completable_type' => $type,
            'completable_id' => $item->id,
        ]));

        $complete('concept', $module->concepts);
        $complete('quiz_question', $module->quizQuestions);
        $complete('challenge', $module->challenges);
        $complete('interview_question', $module->interviewQuestions);

        $module->flashcards->each(fn ($card) => FlashcardReview::create([
            'user_id' => $user->id,
            'flashcard_id' => $card->id,
            'box' => FlashcardReview::LEARNED_BOX,
            'due_on' => today()->addDays(7),
        ]));
    }

    public function test_guests_are_redirected_to_login()
    {
        $analysis = JobAnalysis::factory()->create();

        $this->get(route('jobs.index'))->assertRedirect(route('login'));
        $this->post(route('jobs.store'), ['body' => self::POSTING])->assertRedirect(route('login'));
        $this->get(route('jobs.show', $analysis))->assertRedirect(route('login'));
        $this->delete(route('jobs.destroy', $analysis))->assertRedirect(route('login'));
    }

    public function test_the_index_lists_only_my_analyses_newest_first()
    {
        $me = User::factory()->create();
        $old = JobAnalysis::factory()->for($me)->create(['title' => 'Antigua', 'created_at' => now()->subDays(3)]);
        $new = JobAnalysis::factory()->for($me)->create(['title' => 'Reciente', 'body' => self::POSTING]);
        JobAnalysis::factory()->create(['title' => 'De otra persona']);

        $this->actingAs($me)->get(route('jobs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Jobs')
                ->has('analyses', 2)
                ->where('analyses.0.id', $new->id)
                ->where('analyses.1.id', $old->id)
                ->where('analyses.0.skills', 7)
                ->where('analyses', fn ($analyses) => ! collect($analyses)->pluck('title')->contains('De otra persona')));
    }

    public function test_storing_a_posting_saves_it_and_goes_to_its_analysis()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('jobs.store'), [
            'title' => 'Backend Laravel',
            'body' => self::POSTING,
        ]);

        $analysis = JobAnalysis::firstOrFail();
        $response->assertRedirect(route('jobs.show', $analysis));
        $this->assertSame($user->id, $analysis->user_id);
        $this->assertSame('Backend Laravel', $analysis->title);
    }

    public function test_the_title_is_optional()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('jobs.store'), ['body' => self::POSTING])
            ->assertSessionHasNoErrors();

        $this->assertNull(JobAnalysis::firstOrFail()->title);
    }

    public function test_the_posting_is_validated()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('jobs.store'), [])->assertSessionHasErrors('body');
        $this->actingAs($user)->post(route('jobs.store'), ['body' => 'muy corto'])->assertSessionHasErrors('body');
        $this->actingAs($user)->post(route('jobs.store'), ['body' => str_repeat('a ', 10001)])->assertSessionHasErrors('body');
        $this->actingAs($user)->post(route('jobs.store'), ['title' => str_repeat('a', 121), 'body' => self::POSTING])->assertSessionHasErrors('title');

        $this->assertSame(0, JobAnalysis::count());
    }

    public function test_the_report_separates_what_the_roadmap_teaches_from_what_it_does_not()
    {
        $this->seed(RoadmapSeeder::class);
        $user = User::factory()->create();
        $analysis = JobAnalysis::factory()->for($user)->create(['body' => self::POSTING]);

        $this->actingAs($user)->get(route('jobs.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page
                ->component('JobShow')
                ->where('analysis.id', $analysis->id)
                ->where('report.summary.detected', 7)
                ->where('report.summary.covered', 5)
                ->where('report.summary.outside', 2)
                ->where('report.summary.readiness', 0)
                ->where('report.modules', fn ($modules) => collect($modules)->pluck('slug')->sort()->values()->all() === ['api', 'ops', 'req', 'sql', 'vue'])
                ->where('report.outside', fn ($outside) => collect($outside)->pluck('name')->sort()->values()->all() === ['AWS', 'Kubernetes']));
    }

    public function test_modules_are_listed_by_how_much_the_posting_insists_on_them()
    {
        $this->seed(RoadmapSeeder::class);
        $user = User::factory()->create();
        $analysis = JobAnalysis::factory()->for($user)->create([
            'body' => 'Vue, Vue y más Vue. También Docker. Y Laravel, Laravel.',
        ]);

        $this->actingAs($user)->get(route('jobs.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.modules', fn ($modules) => collect($modules)->pluck('slug')->all() === ['vue', 'req', 'ops'])
                ->where('report.modules.0.mentions', 3));
    }

    public function test_a_module_the_user_has_learned_is_ready_and_the_rest_are_not()
    {
        $this->seed(RoadmapSeeder::class);
        $user = User::factory()->create();
        $this->learnModule($user, 'vue');

        $analysis = JobAnalysis::factory()->for($user)->create(['body' => 'Buscamos Vue y Docker.']);

        $this->actingAs($user)->get(route('jobs.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.modules', fn ($modules) => collect($modules)->firstWhere('slug', 'vue')['status'] === 'ready')
                ->where('report.modules', fn ($modules) => collect($modules)->firstWhere('slug', 'ops')['status'] === 'todo')
                ->where('report.summary.readiness', 50));
    }

    public function test_the_report_follows_the_users_progress()
    {
        $this->seed(RoadmapSeeder::class);
        $user = User::factory()->create();
        $analysis = JobAnalysis::factory()->for($user)->create(['body' => 'Buscamos Vue.']);

        $this->actingAs($user)->get(route('jobs.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page->where('report.summary.readiness', 0));

        $this->learnModule($user, 'vue');

        $this->actingAs($user)->get(route('jobs.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page->where('report.summary.readiness', 100));
    }

    public function test_a_posting_without_known_skills_reports_nothing_to_study()
    {
        $this->seed(RoadmapSeeder::class);
        $user = User::factory()->create();
        $analysis = JobAnalysis::factory()->for($user)->create(['body' => str_repeat('Buscamos una persona proactiva, con ganas de aprender y buen trato. ', 2)]);

        $this->actingAs($user)->get(route('jobs.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.summary.detected', 0)
                ->where('report.summary.readiness', null)
                ->where('report.modules', [])
                ->where('report.outside', []));
    }

    public function test_a_skill_whose_module_does_not_exist_is_reported_as_outside_the_roadmap()
    {
        // No roadmap seeded: nothing can be matched to a module.
        $user = User::factory()->create();
        $analysis = JobAnalysis::factory()->for($user)->create(['body' => self::POSTING]);

        $this->actingAs($user)->get(route('jobs.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.summary.covered', 0)
                ->where('report.summary.outside', 7)
                ->where('report.modules', []));
    }

    public function test_nobody_can_read_someone_elses_analysis()
    {
        $analysis = JobAnalysis::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('jobs.show', $analysis))->assertForbidden();
    }

    public function test_an_unknown_analysis_is_not_found()
    {
        $this->actingAs(User::factory()->create())->get(route('jobs.show', 999999))->assertNotFound();
    }

    public function test_an_analysis_can_be_deleted_by_its_owner_only()
    {
        $owner = User::factory()->create();
        $analysis = JobAnalysis::factory()->for($owner)->create();

        $this->actingAs(User::factory()->create())->delete(route('jobs.destroy', $analysis))->assertForbidden();
        $this->assertModelExists($analysis);

        $this->actingAs($owner)->delete(route('jobs.destroy', $analysis))->assertRedirect(route('jobs.index'));
        $this->assertModelMissing($analysis);
    }

    public function test_deleting_an_analysis_does_not_touch_progress()
    {
        $this->seed(RoadmapSeeder::class);
        $user = User::factory()->create();
        $this->learnModule($user, 'vue');
        $before = Completion::where('user_id', $user->id)->count();
        $analysis = JobAnalysis::factory()->for($user)->create();

        $this->actingAs($user)->delete(route('jobs.destroy', $analysis));

        $this->assertSame($before, Completion::where('user_id', $user->id)->count());
    }
}
