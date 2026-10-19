<?php

namespace App\Policies;

use App\Models\JobAnalysis;
use App\Models\User;

class JobAnalysisPolicy
{
    /**
     * A user only reads their own analyses.
     */
    public function view(User $user, JobAnalysis $analysis): bool
    {
        return $user->id === $analysis->user_id;
    }

    /**
     * A user only deletes their own analyses.
     */
    public function delete(User $user, JobAnalysis $analysis): bool
    {
        return $user->id === $analysis->user_id;
    }
}
