<?php

namespace App\Actions\Study;

use App\Models\StudyDay;
use App\Models\User;

class RecordStudyDay
{
    /**
     * Mark today as a study day for the user. Safe to call repeatedly.
     */
    public function handle(User $user): void
    {
        StudyDay::createOrFirst([
            'user_id' => $user->id,
            'studied_on' => today()->toDateString(),
        ]);
    }
}
