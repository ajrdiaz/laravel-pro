<?php

namespace App\Models;

use Database\Factories\FlashcardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $module_id
 * @property int $position
 * @property string $question
 * @property string $answer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['module_id', 'position', 'question', 'answer'])]
class Flashcard extends Model
{
    /** @use HasFactory<FlashcardFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @return HasMany<FlashcardReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(FlashcardReview::class);
    }

    /**
     * Cards the user has never seen, or whose next review date has arrived.
     *
     * @param  Builder<Flashcard>  $query
     */
    #[Scope]
    protected function dueFor(Builder $query, User $user): void
    {
        $query->whereDoesntHave('reviews', fn (Builder $reviews) => $reviews
            ->whereBelongsTo($user)
            ->where('due_on', '>', today()->toDateString()));
    }
}
