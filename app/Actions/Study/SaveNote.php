<?php

namespace App\Actions\Study;

use App\Models\Module;
use App\Models\Note;
use App\Models\User;

class SaveNote
{
    public function __construct(private RecordStudyDay $recordStudyDay) {}

    /**
     * Keep the user's note on a module. A note with nothing but whitespace is removed, so an
     * emptied textarea leaves no empty row behind. Writing counts as studying today.
     */
    public function handle(User $user, Module $module, ?string $body): void
    {
        $attributes = ['user_id' => $user->id, 'module_id' => $module->id];

        if ($body === null || trim($body) === '') {
            Note::where($attributes)->delete();

            return;
        }

        Note::updateOrCreate($attributes, ['body' => $body]);

        $this->recordStudyDay->handle($user);
    }
}
