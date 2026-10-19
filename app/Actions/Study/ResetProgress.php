<?php

namespace App\Actions\Study;

use App\Models\Completion;
use App\Models\FlashcardReview;
use App\Models\StudyDay;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResetProgress
{
    /**
     * Erase everything the user has studied: completions, card schedule and streak.
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user) {
            Completion::where('user_id', $user->id)->delete();
            FlashcardReview::where('user_id', $user->id)->delete();
            StudyDay::where('user_id', $user->id)->delete();
        });
    }
}
