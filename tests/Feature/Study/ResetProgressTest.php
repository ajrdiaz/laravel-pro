<?php

namespace Tests\Feature\Study;

use App\Models\Completion;
use App\Models\Concept;
use App\Models\Flashcard;
use App\Models\FlashcardReview;
use App\Models\StudyDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetProgressTest extends TestCase
{
    use RefreshDatabase;

    private function studySomething(User $user): void
    {
        $concept = Concept::factory()->create();
        $card = Flashcard::factory()->create();

        Completion::create(['user_id' => $user->id, 'completable_type' => 'concept', 'completable_id' => $concept->id]);
        FlashcardReview::create(['user_id' => $user->id, 'flashcard_id' => $card->id, 'box' => 2, 'due_on' => today()->addDays(3)]);
        StudyDay::create(['user_id' => $user->id, 'studied_on' => today()]);
    }

    public function test_guests_cannot_reset_progress()
    {
        $this->delete(route('progress.destroy'))->assertRedirect(route('login'));
    }

    public function test_it_erases_the_users_completions_card_schedule_and_streak()
    {
        $user = User::factory()->create();
        $this->studySomething($user);

        $this->actingAs($user)->delete(route('progress.destroy'))->assertRedirect(route('dashboard'));

        $this->assertSame(0, Completion::where('user_id', $user->id)->count());
        $this->assertSame(0, FlashcardReview::where('user_id', $user->id)->count());
        $this->assertSame(0, StudyDay::where('user_id', $user->id)->count());
    }

    public function test_it_leaves_other_users_and_the_content_alone()
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $this->studySomething($me);
        $this->studySomething($other);

        $this->actingAs($me)->delete(route('progress.destroy'));

        $this->assertSame(1, Completion::where('user_id', $other->id)->count());
        $this->assertSame(1, FlashcardReview::where('user_id', $other->id)->count());
        $this->assertSame(1, StudyDay::where('user_id', $other->id)->count());
        $this->assertSame(2, Concept::count());
        $this->assertSame(2, Flashcard::count());
    }
}
