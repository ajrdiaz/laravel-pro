<?php

namespace App\Models;

use Database\Factories\InterviewQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $module_id
 * @property int $position
 * @property string $question
 * @property list<string> $points
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['module_id', 'position', 'question', 'points'])]
class InterviewQuestion extends Model
{
    /** @use HasFactory<InterviewQuestionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
