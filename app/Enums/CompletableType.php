<?php

namespace App\Enums;

use App\Models\Challenge;
use App\Models\Concept;
use App\Models\InterviewQuestion;
use App\Models\Lesson;
use App\Models\ProjectMilestone;
use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Model;

/**
 * Study items a user can complete. The value doubles as the morph alias
 * stored in `completions.completable_type`.
 */
enum CompletableType: string
{
    case Concept = 'concept';
    case Challenge = 'challenge';
    case InterviewQuestion = 'interview_question';
    case QuizQuestion = 'quiz_question';
    case Lesson = 'lesson';
    case ProjectMilestone = 'milestone';

    /**
     * @return class-string<Model>
     */
    public function model(): string
    {
        return match ($this) {
            self::Concept => Concept::class,
            self::Challenge => Challenge::class,
            self::InterviewQuestion => InterviewQuestion::class,
            self::QuizQuestion => QuizQuestion::class,
            self::Lesson => Lesson::class,
            self::ProjectMilestone => ProjectMilestone::class,
        };
    }

    /**
     * Types the user ticks by hand. Quiz questions are completed by answering them.
     *
     * @return list<string>
     */
    public static function manual(): array
    {
        return [
            self::Concept->value,
            self::Challenge->value,
            self::InterviewQuestion->value,
            self::Lesson->value,
            self::ProjectMilestone->value,
        ];
    }
}
