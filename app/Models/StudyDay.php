<?php

namespace App\Models;

use App\Casts\PlainDate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A calendar day on which the user studied something; the source of the streak.
 *
 * @property int $id
 * @property int $user_id
 * @property CarbonInterface $studied_on
 */
#[Fillable(['user_id', 'studied_on'])]
class StudyDay extends Model
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
            'studied_on' => PlainDate::class,
        ];
    }
}
