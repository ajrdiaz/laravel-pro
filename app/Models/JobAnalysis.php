<?php

namespace App\Models;

use Database\Factories\JobAnalysisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A job posting the user pasted to see which roadmap topics it asks for. Only the text
 * is stored: the result is recomputed on each view, so it follows the user's progress.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $title
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'title', 'body'])]
class JobAnalysis extends Model
{
    /** @use HasFactory<JobAnalysisFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
