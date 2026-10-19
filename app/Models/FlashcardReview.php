<?php

namespace App\Models;

use App\Casts\PlainDate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Leitner-box state of one flashcard for one user.
 *
 * @property int $id
 * @property int $user_id
 * @property int $flashcard_id
 * @property int $box
 * @property CarbonInterface $due_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'flashcard_id', 'box', 'due_on'])]
class FlashcardReview extends Model
{
    /** Days until the next review, indexed by box. A miss sends the card back to box 0. */
    public const array INTERVALS = [0, 1, 3, 7, 14, 30];

    public const int MAX_BOX = 5;

    /** From this box on, the card counts as learned in the module progress. */
    public const int LEARNED_BOX = 3;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'box' => 'integer',
            'due_on' => PlainDate::class,
        ];
    }

    /**
     * @return BelongsTo<Flashcard, $this>
     */
    public function flashcard(): BelongsTo
    {
        return $this->belongsTo(Flashcard::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
