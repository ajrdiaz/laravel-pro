<?php

namespace Tests\Feature\Study;

use App\Models\Flashcard;
use App\Models\FlashcardReview;
use App\Models\Module;
use App\Models\StudyDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FlashcardReviewTest extends TestCase
{
    use RefreshDatabase;

    private function review(User $user, Flashcard $card, bool $knew)
    {
        return $this->actingAs($user)->post(route('flashcards.review', $card), ['knew' => $knew]);
    }

    public function test_guests_cannot_review_cards()
    {
        $card = Flashcard::factory()->create();

        $this->post(route('flashcards.review', $card), ['knew' => true])->assertRedirect(route('login'));
    }

    public function test_knowing_a_new_card_puts_it_in_box_one_for_tomorrow()
    {
        $user = User::factory()->create();
        $card = Flashcard::factory()->create();

        $this->review($user, $card, true)->assertRedirect();

        $review = FlashcardReview::firstWhere(['user_id' => $user->id, 'flashcard_id' => $card->id]);
        $this->assertSame(1, $review->box);
        $this->assertSame(today()->addDay()->toDateString(), $review->due_on->toDateString());
    }

    public function test_each_correct_answer_climbs_a_box_with_a_longer_wait()
    {
        $user = User::factory()->create();
        $card = Flashcard::factory()->create();

        foreach (FlashcardReview::INTERVALS as $box => $days) {
            if ($box === 0) {
                continue;
            }

            $this->review($user, $card, true);

            $review = FlashcardReview::firstWhere('flashcard_id', $card->id);
            $this->assertSame($box, $review->box);
            $this->assertSame(today()->addDays($days)->toDateString(), $review->due_on->toDateString());
        }
    }

    public function test_the_box_never_goes_past_the_last_one()
    {
        $user = User::factory()->create();
        $card = Flashcard::factory()->create();

        foreach (range(1, FlashcardReview::MAX_BOX + 3) as $ignored) {
            $this->review($user, $card, true);
        }

        $review = FlashcardReview::firstWhere('flashcard_id', $card->id);
        $this->assertSame(FlashcardReview::MAX_BOX, $review->box);
        $this->assertSame(today()->addDays(30)->toDateString(), $review->due_on->toDateString());
    }

    public function test_missing_a_card_sends_it_back_to_box_zero_and_due_today()
    {
        $user = User::factory()->create();
        $card = Flashcard::factory()->create();
        FlashcardReview::create(['user_id' => $user->id, 'flashcard_id' => $card->id, 'box' => 4, 'due_on' => today()->addDays(14)]);

        $this->review($user, $card, false);

        $review = FlashcardReview::firstWhere('flashcard_id', $card->id);
        $this->assertSame(0, $review->box);
        $this->assertSame(today()->toDateString(), $review->due_on->toDateString());
    }

    public function test_reviewing_a_card_counts_as_a_study_day_once()
    {
        $user = User::factory()->create();
        $cards = Flashcard::factory()->count(2)->create();

        $this->review($user, $cards[0], true);
        $this->review($user, $cards[1], false);

        $this->assertSame(1, StudyDay::where('user_id', $user->id)->count());
    }

    public function test_one_users_review_does_not_affect_another()
    {
        $card = Flashcard::factory()->create();
        $this->review(User::factory()->create(), $card, true);

        $other = User::factory()->create();

        $this->assertSame(0, FlashcardReview::where('user_id', $other->id)->count());
        $this->assertSame(1, Flashcard::dueFor($other)->count());
    }

    public function test_the_knew_field_is_required()
    {
        $card = Flashcard::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('flashcards.review', $card))
            ->assertSessionHasErrors('knew');
    }

    public function test_the_page_lists_only_due_cards_unless_asked_for_all()
    {
        $user = User::factory()->create();
        $module = Module::factory()->create();
        [$due, $later] = Flashcard::factory()->count(2)->create(['module_id' => $module->id]);
        FlashcardReview::create(['user_id' => $user->id, 'flashcard_id' => $later->id, 'box' => 2, 'due_on' => today()->addDays(3)]);

        $this->actingAs($user)->get(route('flashcards.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Flashcards')
                ->where('total', 2)
                ->has('cards', 1)
                ->where('cards.0.id', $due->id)
                ->where('cards.0.box', 0));

        $this->actingAs($user)->get(route('flashcards.index', ['all' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('cards', 2)
                ->where('filters.all', true));
    }

    public function test_the_page_can_be_narrowed_to_one_module()
    {
        $user = User::factory()->create();
        $wanted = Module::factory()->create();
        Flashcard::factory()->count(2)->create(['module_id' => $wanted->id]);
        Flashcard::factory()->create();

        $this->actingAs($user)->get(route('flashcards.index', ['module' => $wanted->slug]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.module', $wanted->slug)
                ->where('total', 2)
                ->has('cards', 2));
    }

    public function test_an_unknown_module_falls_back_to_all_modules()
    {
        $user = User::factory()->create();
        Flashcard::factory()->count(3)->create();

        $this->actingAs($user)->get(route('flashcards.index', ['module' => 'nope']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.module', 'all')
                ->has('cards', 3));
    }
}
