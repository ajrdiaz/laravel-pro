<?php

namespace App\Models;

use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property int $position
 * @property string $section The heading the module is listed under, such as "Frontend".
 * @property string $command The terminal command shown as the module's label.
 * @property string $title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'position', 'section', 'command', 'title'])]
class Module extends Model
{
    /** @use HasFactory<ModuleFactory> */
    use HasFactory;

    /**
     * @return HasMany<Concept, $this>
     */
    public function concepts(): HasMany
    {
        return $this->hasMany(Concept::class)->orderBy('position');
    }

    /**
     * @return HasMany<Flashcard, $this>
     */
    public function flashcards(): HasMany
    {
        return $this->hasMany(Flashcard::class)->orderBy('position');
    }

    /**
     * @return HasMany<QuizQuestion, $this>
     */
    public function quizQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position');
    }

    /**
     * @return HasMany<Challenge, $this>
     */
    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class)->orderBy('position');
    }

    /**
     * @return HasMany<InterviewQuestion, $this>
     */
    public function interviewQuestions(): HasMany
    {
        return $this->hasMany(InterviewQuestion::class)->orderBy('position');
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }

    /**
     * @return HasMany<LearningResource, $this>
     */
    public function learningResources(): HasMany
    {
        return $this->hasMany(LearningResource::class)->orderBy('position');
    }
}
