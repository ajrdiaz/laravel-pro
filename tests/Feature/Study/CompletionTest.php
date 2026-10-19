<?php

namespace Tests\Feature\Study;

use App\Models\Challenge;
use App\Models\Completion;
use App\Models\Concept;
use App\Models\InterviewQuestion;
use App\Models\QuizQuestion;
use App\Models\StudyDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletionTest extends TestCase
{
    use RefreshDatabase;

    private function toggle(User $user, string $type, int $id, bool $completed)
    {
        return $this->actingAs($user)->put(route('completions.update', [$type, $id]), ['completed' => $completed]);
    }

    public function test_guests_are_redirected_to_login()
    {
        $concept = Concept::factory()->create();

        $this->put(route('completions.update', ['concept', $concept->id]), ['completed' => true])
            ->assertRedirect(route('login'));
    }

    public function test_concepts_challenges_and_interview_questions_can_be_completed()
    {
        $user = User::factory()->create();

        $items = [
            'concept' => Concept::factory()->create(),
            'challenge' => Challenge::factory()->create(),
            'interview_question' => InterviewQuestion::factory()->create(),
        ];

        foreach ($items as $type => $item) {
            $this->toggle($user, $type, $item->id, true)->assertRedirect();

            $this->assertDatabaseHas('completions', [
                'user_id' => $user->id,
                'completable_type' => $type,
                'completable_id' => $item->id,
            ]);
        }
    }

    public function test_completing_something_counts_as_a_study_day()
    {
        $user = User::factory()->create();
        $concept = Concept::factory()->create();

        $this->toggle($user, 'concept', $concept->id, true);

        $this->assertSame(1, StudyDay::where('user_id', $user->id)->count());
    }

    public function test_completing_twice_keeps_a_single_row()
    {
        $user = User::factory()->create();
        $concept = Concept::factory()->create();

        $this->toggle($user, 'concept', $concept->id, true);
        $this->toggle($user, 'concept', $concept->id, true);

        $this->assertSame(1, Completion::where('user_id', $user->id)->count());
    }

    public function test_an_item_can_be_unticked()
    {
        $user = User::factory()->create();
        $concept = Concept::factory()->create();
        $this->toggle($user, 'concept', $concept->id, true);

        $this->toggle($user, 'concept', $concept->id, false);

        $this->assertSame(0, Completion::where('user_id', $user->id)->count());
    }

    public function test_unticking_does_not_count_as_studying()
    {
        $user = User::factory()->create();
        $concept = Concept::factory()->create();

        $this->toggle($user, 'concept', $concept->id, false);

        $this->assertSame(0, StudyDay::where('user_id', $user->id)->count());
    }

    public function test_unticking_leaves_other_users_completions_alone()
    {
        $concept = Concept::factory()->create();
        $mine = User::factory()->create();
        $theirs = User::factory()->create();
        $this->toggle($mine, 'concept', $concept->id, true);
        $this->toggle($theirs, 'concept', $concept->id, true);

        $this->toggle($mine, 'concept', $concept->id, false);

        $this->assertSame(0, Completion::where('user_id', $mine->id)->count());
        $this->assertSame(1, Completion::where('user_id', $theirs->id)->count());
    }

    public function test_quiz_questions_cannot_be_ticked_by_hand()
    {
        $question = QuizQuestion::factory()->create();

        $this->toggle(User::factory()->create(), 'quiz_question', $question->id, true)->assertNotFound();
    }

    public function test_unknown_types_and_ids_are_not_found()
    {
        $user = User::factory()->create();

        $this->toggle($user, 'banana', 1, true)->assertNotFound();
        $this->toggle($user, 'concept', 999999, true)->assertNotFound();
    }

    public function test_the_completed_field_is_required()
    {
        $concept = Concept::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('completions.update', ['concept', $concept->id]))
            ->assertSessionHasErrors('completed');
    }
}
