<?php

namespace App\Services;

use App\Enums\CompletableType;
use App\Models\Completion;
use App\Models\Flashcard;
use App\Models\FlashcardReview;
use App\Models\Module;
use App\Models\StudyDay;
use App\Models\User;
use Illuminate\Support\Collection;

class ProgressReport
{
    /**
     * Each module with how much of it the user has completed. A module is made of its
     * lessons, concepts, flashcards (learned from box 3 on), quiz questions, challenges
     * and interview questions, all weighted equally.
     *
     * @return Collection<int, ModuleProgress>
     */
    public function modules(User $user): Collection
    {
        $learned = $this->idSet(
            FlashcardReview::where('user_id', $user->id)
                ->where('box', '>=', FlashcardReview::LEARNED_BOX)
                ->pluck('flashcard_id'),
        );

        // Only what belongs to a module counts: project milestones are practice on top of it.
        $lessons = $this->idSet($this->completedIds($user, CompletableType::Lesson));
        $concepts = $this->idSet($this->completedIds($user, CompletableType::Concept));
        $quizQuestions = $this->idSet($this->completedIds($user, CompletableType::QuizQuestion));
        $challenges = $this->idSet($this->completedIds($user, CompletableType::Challenge));
        $interviewQuestions = $this->idSet($this->completedIds($user, CompletableType::InterviewQuestion));

        return Module::query()
            ->orderBy('position')
            ->with(['lessons:id,module_id', 'concepts:id,module_id', 'flashcards:id,module_id', 'quizQuestions:id,module_id', 'challenges:id,module_id', 'interviewQuestions:id,module_id'])
            ->get()
            ->map(function (Module $module) use ($learned, $lessons, $concepts, $quizQuestions, $challenges, $interviewQuestions) {
                $doneIn = fn (Collection $items, array $ids) => $items->filter(fn ($item) => isset($ids[$item->id]))->count();

                $done = $doneIn($module->lessons, $lessons)
                    + $doneIn($module->concepts, $concepts)
                    + $doneIn($module->flashcards, $learned)
                    + $doneIn($module->quizQuestions, $quizQuestions)
                    + $doneIn($module->challenges, $challenges)
                    + $doneIn($module->interviewQuestions, $interviewQuestions);

                $total = $module->lessons->count()
                    + $module->concepts->count()
                    + $module->flashcards->count()
                    + $module->quizQuestions->count()
                    + $module->challenges->count()
                    + $module->interviewQuestions->count();

                return new ModuleProgress(
                    id: $module->id,
                    slug: $module->slug,
                    position: $module->position,
                    section: $module->section,
                    command: $module->command,
                    title: $module->title,
                    done: $done,
                    total: $total,
                );
            })
            ->values();
    }

    /**
     * Headline numbers shown at the top of every page.
     *
     * @param  Collection<int, ModuleProgress>|null  $modules  Pass the result of modules() to avoid computing it twice.
     * @return array{progress: int, due_cards: int, streak: int}
     */
    public function stats(User $user, ?Collection $modules = null): array
    {
        $modules ??= $this->modules($user);

        $done = $modules->sum(fn (ModuleProgress $module) => $module->done);
        $total = $modules->sum(fn (ModuleProgress $module) => $module->total);

        return [
            'progress' => $total > 0 ? (int) round($done * 100 / $total) : 0,
            'due_cards' => Flashcard::dueFor($user)->count(),
            'streak' => $this->streak($user),
        ];
    }

    /**
     * @return Collection<int, int>
     */
    public function completedIds(User $user, CompletableType $type): Collection
    {
        return Completion::where('user_id', $user->id)
            ->where('completable_type', $type->value)
            ->pluck('completable_id');
    }

    /**
     * Consecutive study days ending today, or yesterday if the user hasn't studied yet today.
     */
    private function streak(User $user): int
    {
        $days = StudyDay::where('user_id', $user->id)
            ->get()
            ->mapWithKeys(fn (StudyDay $day) => [$day->studied_on->toDateString() => true]);

        $cursor = today();

        if (! $days->has($cursor->toDateString())) {
            $cursor = $cursor->subDay();
        }

        $streak = 0;

        while ($days->has($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return array<int, true>
     */
    private function idSet(Collection $ids): array
    {
        return array_fill_keys($ids->all(), true);
    }
}
