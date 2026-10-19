<?php

namespace App\Actions\Study;

use App\Models\Completion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ToggleCompletion
{
    public function __construct(private RecordStudyDay $recordStudyDay) {}

    /**
     * Mark a study item as completed (or not) for the user.
     */
    public function handle(User $user, Model $completable, bool $completed): void
    {
        $attributes = [
            'user_id' => $user->id,
            'completable_type' => $completable->getMorphClass(),
            'completable_id' => $completable->getKey(),
        ];

        if (! $completed) {
            Completion::where($attributes)->delete();

            return;
        }

        Completion::createOrFirst($attributes, ['completed_at' => now()]);

        $this->recordStudyDay->handle($user);
    }
}
