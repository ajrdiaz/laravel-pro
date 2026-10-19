<?php

namespace Tests\Feature\Study;

use App\Models\Completion;
use App\Models\Module;
use App\Models\QuizQuestion;
use App\Models\StudyDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuizAnswerTest extends TestCase
{
    use RefreshDatabase;

    private function answer(User $user, QuizQuestion $question, int $selected)
    {
        return $this->actingAs($user)->post(route('quiz.answer', $question), ['selected' => $selected]);
    }

    private function completions(User $user): int
    {
        return Completion::where(['user_id' => $user->id, 'completable_type' => 'quiz_question'])->count();
    }

    public function test_guests_cannot_answer()
    {
        $question = QuizQuestion::factory()->create();

        $this->post(route('quiz.answer', $question), ['selected' => 0])->assertRedirect(route('login'));
    }

    public function test_a_correct_answer_completes_the_question_and_counts_as_a_study_day()
    {
        $user = User::factory()->create();
        $question = QuizQuestion::factory()->create(['correct_index' => 2]);

        $this->answer($user, $question, 2)->assertRedirect();

        $this->assertSame(1, $this->completions($user));
        $this->assertSame(1, StudyDay::where('user_id', $user->id)->count());
    }

    public function test_a_wrong_answer_completes_nothing_but_still_counts_as_a_study_day()
    {
        $user = User::factory()->create();
        $question = QuizQuestion::factory()->create(['correct_index' => 2]);

        $this->answer($user, $question, 1);

        $this->assertSame(0, $this->completions($user));
        $this->assertSame(1, StudyDay::where('user_id', $user->id)->count());
    }

    public function test_a_later_wrong_answer_does_not_undo_a_correct_one()
    {
        $user = User::factory()->create();
        $question = QuizQuestion::factory()->create(['correct_index' => 0]);

        $this->answer($user, $question, 0);
        $this->answer($user, $question, 3);

        $this->assertSame(1, $this->completions($user));
    }

    public function test_answering_correctly_twice_does_not_duplicate_the_completion()
    {
        $user = User::factory()->create();
        $question = QuizQuestion::factory()->create();

        $this->answer($user, $question, 0);
        $this->answer($user, $question, 0);

        $this->assertSame(1, $this->completions($user));
    }

    public function test_the_selected_option_is_required()
    {
        $question = QuizQuestion::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('quiz.answer', $question))
            ->assertSessionHasErrors('selected');
    }

    public function test_an_all_modules_round_is_capped_at_six_questions()
    {
        QuizQuestion::factory()->count(8)->create();

        $this->actingAs(User::factory()->create())->get(route('quiz.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Quiz')
                ->has('questions', 6)
                ->has('questions.0', fn (Assert $question) => $question
                    ->hasAll(['id', 'question', 'options', 'correct_index', 'explanation', 'module'])));
    }

    public function test_a_module_round_has_all_of_its_questions()
    {
        $module = Module::factory()->create();
        QuizQuestion::factory()->count(8)->create(['module_id' => $module->id]);
        QuizQuestion::factory()->count(2)->create();

        $this->actingAs(User::factory()->create())->get(route('quiz.index', ['module' => $module->slug]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.module', $module->slug)
                ->has('questions', 8));
    }
}
