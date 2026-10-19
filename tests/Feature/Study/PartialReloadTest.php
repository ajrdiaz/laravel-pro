<?php

namespace Tests\Feature\Study;

use App\Models\Flashcard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartialReloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_answering_a_card_only_needs_the_stats_to_be_reloaded()
    {
        Flashcard::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('flashcards.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Flashcards')
                ->has('cards', 1)
                ->has('stats')
                // The page asks for `only: ['stats']` after each answer: nothing else may be needed.
                ->reloadOnly('stats', fn (Assert $reload) => $reload
                    ->has('stats')
                    ->missing('cards')
                    ->missing('modules')));
    }

    public function test_resetting_progress_tells_the_user_it_worked()
    {
        $this->actingAs(User::factory()->create())
            ->delete(route('progress.destroy'))
            ->assertRedirect(route('dashboard'))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Progreso borrado.'])
            ->assertInertiaFlashMissing('error');
    }
}
