<?php

namespace Tests\Feature\Study;

use App\Models\Challenge;
use App\Models\Completion;
use App\Models\Concept;
use App\Models\Flashcard;
use App\Models\FlashcardReview;
use App\Models\InterviewQuestion;
use App\Models\Module;
use App\Models\QuizQuestion;
use App\Models\StudyDay;
use App\Models\User;
use App\Services\ProgressReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressReportTest extends TestCase
{
    use RefreshDatabase;

    private ProgressReport $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->report = new ProgressReport;
    }

    /**
     * A module with 6 items: 2 concepts, 1 flashcard, 1 quiz question, 1 challenge, 1 interview question.
     */
    private function moduleWithSixItems(): Module
    {
        $module = Module::factory()->create();

        $concepts = Concept::factory()->count(2)->create(['module_id' => $module->id]);
        Flashcard::factory()->create(['module_id' => $module->id]);
        QuizQuestion::factory()->create(['module_id' => $module->id]);
        Challenge::factory()->create(['module_id' => $module->id]);
        InterviewQuestion::factory()->create(['module_id' => $module->id]);

        return $module->setRelation('concepts', $concepts);
    }

    private function complete(User $user, string $type, int $id): void
    {
        Completion::create(['user_id' => $user->id, 'completable_type' => $type, 'completable_id' => $id]);
    }

    public function test_a_module_with_no_activity_is_at_zero()
    {
        $this->moduleWithSixItems();

        $progress = $this->report->modules(User::factory()->create())->first()->toArray()['progress'];

        $this->assertSame(['done' => 0, 'total' => 6, 'pct' => 0], $progress);
    }

    public function test_concepts_quiz_challenge_interview_and_learned_cards_all_count()
    {
        $user = User::factory()->create();
        $module = $this->moduleWithSixItems();

        $this->complete($user, 'concept', $module->concepts[0]->id);
        $this->complete($user, 'quiz_question', $module->quizQuestions()->firstOrFail()->id);
        $this->complete($user, 'challenge', $module->challenges()->firstOrFail()->id);
        $this->complete($user, 'interview_question', $module->interviewQuestions()->firstOrFail()->id);
        FlashcardReview::create([
            'user_id' => $user->id,
            'flashcard_id' => $module->flashcards()->firstOrFail()->id,
            'box' => FlashcardReview::LEARNED_BOX,
            'due_on' => today()->addDays(7),
        ]);

        $progress = $this->report->modules($user)->first()->toArray()['progress'];

        $this->assertSame(['done' => 5, 'total' => 6, 'pct' => 83], $progress);
    }

    public function test_a_card_below_the_learned_box_does_not_count()
    {
        $user = User::factory()->create();
        $module = $this->moduleWithSixItems();

        FlashcardReview::create([
            'user_id' => $user->id,
            'flashcard_id' => $module->flashcards()->firstOrFail()->id,
            'box' => FlashcardReview::LEARNED_BOX - 1,
            'due_on' => today()->addDay(),
        ]);

        $this->assertSame(0, $this->report->modules($user)->first()->done);
    }

    public function test_other_users_progress_is_not_counted()
    {
        $module = $this->moduleWithSixItems();
        $this->complete(User::factory()->create(), 'concept', $module->concepts[0]->id);

        $progress = $this->report->modules(User::factory()->create())->first()->toArray()['progress'];

        $this->assertSame(0, $progress['done']);
    }

    public function test_overall_progress_adds_up_every_module()
    {
        $user = User::factory()->create();
        $first = $this->moduleWithSixItems();
        $this->moduleWithSixItems();

        foreach ($first->concepts as $concept) {
            $this->complete($user, 'concept', $concept->id);
        }

        // 2 of 12 items done.
        $this->assertSame(17, $this->report->stats($user)['progress']);
    }

    public function test_a_catalogue_without_modules_reports_zero_instead_of_dividing_by_zero()
    {
        $this->assertSame(0, $this->report->stats(User::factory()->create())['progress']);
    }

    public function test_streak_is_zero_without_study_days()
    {
        $this->assertSame(0, $this->report->stats(User::factory()->create())['streak']);
    }

    public function test_streak_counts_consecutive_days_ending_today()
    {
        $user = User::factory()->create();

        foreach ([0, 1, 2] as $daysAgo) {
            StudyDay::create(['user_id' => $user->id, 'studied_on' => today()->subDays($daysAgo)]);
        }

        $this->assertSame(3, $this->report->stats($user)['streak']);
    }

    public function test_streak_survives_until_the_end_of_the_day_after_studying()
    {
        $user = User::factory()->create();

        foreach ([1, 2] as $daysAgo) {
            StudyDay::create(['user_id' => $user->id, 'studied_on' => today()->subDays($daysAgo)]);
        }

        $this->assertSame(2, $this->report->stats($user)['streak']);
    }

    public function test_streak_breaks_after_a_missed_day()
    {
        $user = User::factory()->create();

        foreach ([0, 2, 3] as $daysAgo) {
            StudyDay::create(['user_id' => $user->id, 'studied_on' => today()->subDays($daysAgo)]);
        }

        $this->assertSame(1, $this->report->stats($user)['streak']);
    }

    public function test_streak_is_gone_when_the_last_study_day_was_two_days_ago()
    {
        $user = User::factory()->create();
        StudyDay::create(['user_id' => $user->id, 'studied_on' => today()->subDays(2)]);

        $this->assertSame(0, $this->report->stats($user)['streak']);
    }

    public function test_due_cards_are_the_unseen_ones_and_those_scheduled_for_today_or_earlier()
    {
        $user = User::factory()->create();
        $module = Module::factory()->create();
        [$unseen, $dueToday, $overdue, $later] = Flashcard::factory()->count(4)->create(['module_id' => $module->id]);

        $schedule = fn (Flashcard $card, $dueOn) => FlashcardReview::create([
            'user_id' => $user->id,
            'flashcard_id' => $card->id,
            'box' => 1,
            'due_on' => $dueOn,
        ]);
        $schedule($dueToday, today());
        $schedule($overdue, today()->subDays(3));
        $schedule($later, today()->addDay());

        $this->assertSame(3, $this->report->stats($user)['due_cards']);
        $this->assertEqualsCanonicalizing(
            [$unseen->id, $dueToday->id, $overdue->id],
            Flashcard::dueFor($user)->pluck('id')->all(),
        );
    }
}
