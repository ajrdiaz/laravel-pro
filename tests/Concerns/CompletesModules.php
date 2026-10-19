<?php

namespace Tests\Concerns;

use App\Enums\CompletableType;
use App\Models\Completion;
use App\Models\FlashcardReview;
use App\Models\Module;
use App\Models\User;

/**
 * Lets a test put a user at exactly 100% in a module, whatever the module is made of.
 */
trait CompletesModules
{
    protected function completeModule(User $user, string $slug): void
    {
        $module = Module::where('slug', $slug)->firstOrFail();

        $tick = fn (CompletableType $type, iterable $items) => collect($items)->each(
            fn ($item) => Completion::create([
                'user_id' => $user->id,
                'completable_type' => $type->value,
                'completable_id' => $item->id,
            ]),
        );

        $tick(CompletableType::Lesson, $module->lessons);
        $tick(CompletableType::Concept, $module->concepts);
        $tick(CompletableType::QuizQuestion, $module->quizQuestions);
        $tick(CompletableType::Challenge, $module->challenges);
        $tick(CompletableType::InterviewQuestion, $module->interviewQuestions);

        $module->flashcards->each(fn ($card) => FlashcardReview::create([
            'user_id' => $user->id,
            'flashcard_id' => $card->id,
            'box' => FlashcardReview::LEARNED_BOX,
            'due_on' => today()->addDays(7),
        ]));
    }
}
