<?php

namespace App\Actions\Study;

use App\Models\Flashcard;
use App\Models\FlashcardReview;
use App\Models\User;

class ReviewFlashcard
{
    public function __construct(private RecordStudyDay $recordStudyDay) {}

    /**
     * Move the card up one Leitner box when known, or back to box 0 when missed,
     * and schedule its next review.
     */
    public function handle(User $user, Flashcard $flashcard, bool $knew): FlashcardReview
    {
        $review = FlashcardReview::firstOrNew([
            'user_id' => $user->id,
            'flashcard_id' => $flashcard->id,
        ]);

        $box = $knew ? min(FlashcardReview::MAX_BOX, ($review->box ?? 0) + 1) : 0;

        $review->fill([
            'box' => $box,
            'due_on' => today()->addDays(FlashcardReview::INTERVALS[$box]),
        ])->save();

        $this->recordStudyDay->handle($user);

        return $review;
    }
}
