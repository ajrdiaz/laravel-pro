<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A study item (concept, challenge, interview question, quiz question) a user has completed.
 *
 * @property int $id
 * @property int $user_id
 * @property string $completable_type
 * @property int $completable_id
 * @property Carbon $completed_at
 */
#[Fillable(['user_id', 'completable_type', 'completable_id', 'completed_at'])]
class Completion extends Model
{
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function completable(): MorphTo
    {
        return $this->morphTo();
    }
}
