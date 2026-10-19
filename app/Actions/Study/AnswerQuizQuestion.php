<?php

namespace App\Actions\Study;

use App\Models\QuizQuestion;
use App\Models\User;

class AnswerQuizQuestion
{
    public function __construct(
        private ToggleCompletion $toggleCompletion,
        private RecordStudyDay $recordStudyDay,
    ) {}

    /**
     * Record an answer. A question counts as completed once it has been answered
     * correctly; a later wrong answer never takes that away.
     *
     * @return bool Whether the selected option was the right one.
     */
    public function handle(User $user, QuizQuestion $question, int $selected): bool
    {
        $correct = $selected === $question->correct_index;

        if ($correct) {
            $this->toggleCompletion->handle($user, $question, true);
        } else {
            $this->recordStudyDay->handle($user);
        }

        return $correct;
    }
}
