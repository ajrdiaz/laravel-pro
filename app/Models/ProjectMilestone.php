<?php

namespace App\Models;

use Database\Factories\ProjectMilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One step of a project, with what "done" looks like.
 *
 * @property int $id
 * @property int $project_id
 * @property int $position
 * @property string $title
 * @property string $detail
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'position', 'title', 'detail'])]
class ProjectMilestone extends Model
{
    /** @use HasFactory<ProjectMilestoneFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
