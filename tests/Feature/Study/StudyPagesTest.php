<?php

namespace Tests\Feature\Study;

use App\Models\Completion;
use App\Models\Concept;
use App\Models\Flashcard;
use App\Models\FlashcardReview;
use App\Models\StudyDay;
use App\Models\User;
use Database\Seeders\RoadmapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ReadsContentFiles;
use Tests\TestCase;

class StudyPagesTest extends TestCase
{
    use ReadsContentFiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoadmapSeeder::class);
    }

    public function test_the_study_pages_require_a_verified_user()
    {
        foreach (['dashboard', 'flashcards.index', 'quiz.index', 'challenges.index', 'interview.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_the_roadmap_lists_every_module_with_its_concepts_and_a_fresh_progress()
    {
        $first = $this->roadmap()->first();

        // No lessons are seeded here, so a module is its concepts, cards, quiz questions, challenge and interview question.
        $total = count($first['concepts']) + count($first['flashcards']) + count($first['quiz']) + 2;

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Roadmap')
                ->has('modules', $this->roadmap()->count())
                ->where('modules.0.slug', $first['slug'])
                ->where('modules.0.section', $first['section'])
                ->where('modules.0.command', $first['command'])
                ->where('modules.0.progress', ['done' => 0, 'total' => $total, 'pct' => 0])
                ->has('modules.0.concepts', count($first['concepts']))
                ->where('modules.0.concepts.0.completed', false)
                ->where('stats', ['progress' => 0, 'due_cards' => $this->roadmapTotal('flashcards'), 'streak' => 0]));
    }

    public function test_the_roadmap_lists_the_modules_in_order_and_grouped_by_section()
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('modules', fn ($modules) => collect($modules)->pluck('slug')->all() === $this->roadmap()->pluck('slug')->all())
                ->where('modules', fn ($modules) => collect($modules)->pluck('section')->all() === $this->roadmap()->pluck('section')->all()));
    }

    public function test_the_roadmap_reflects_what_the_user_has_completed()
    {
        $user = User::factory()->create();
        $concept = Concept::orderBy('id')->firstOrFail();
        Completion::create(['user_id' => $user->id, 'completable_type' => 'concept', 'completable_id' => $concept->id]);
        StudyDay::create(['user_id' => $user->id, 'studied_on' => today()]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('modules.0.concepts.0.completed', true)
                ->where('modules.0.concepts.1.completed', false)
                ->where('modules.0.progress.done', 1)
                ->where('stats.streak', 1));
    }

    public function test_the_stats_are_shared_with_every_page()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.due_cards', $this->roadmapTotal('flashcards')));
    }

    public function test_the_challenges_page_lists_one_challenge_per_module()
    {
        $this->actingAs(User::factory()->create())->get(route('challenges.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Challenges')
                ->has('challenges', $this->roadmap()->count())
                ->has('challenges.0', fn (Assert $challenge) => $challenge
                    ->hasAll(['id', 'body', 'completed', 'module.title', 'module.command'])));
    }

    public function test_the_interview_page_lists_one_question_per_module_with_its_key_points()
    {
        $this->actingAs(User::factory()->create())->get(route('interview.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Interview')
                ->has('questions', $this->roadmap()->count())
                ->has('questions.0.points')
                ->where('questions.0.completed', false));
    }

    public function test_cards_scheduled_for_the_future_are_not_counted_as_due()
    {
        $user = User::factory()->create();
        FlashcardReview::create([
            'user_id' => $user->id,
            'flashcard_id' => Flashcard::orderBy('id')->firstOrFail()->id,
            'box' => 1,
            'due_on' => today()->addDay(),
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.due_cards', $this->roadmapTotal('flashcards') - 1));
    }
}
