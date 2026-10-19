<?php

namespace App\Models;

use App\Enums\ProjectLevel;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A portfolio project to build, broken into milestones the user ticks off. Projects are
 * practice, not part of a module, so they don't count in the roadmap's progress.
 *
 * @property int $id
 * @property string $slug
 * @property int $position
 * @property ProjectLevel $level
 * @property string $title
 * @property string $summary
 * @property int $hours Rough effort, in hours.
 * @property int|null $stack_id
 * @property list<string> $deliverables What to have at the end, to show.
 * @property list<string> $stretch Optional extras once the milestones are done.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'position', 'level', 'title', 'summary', 'hours', 'stack_id', 'deliverables', 'stretch'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => ProjectLevel::class,
            'deliverables' => 'array',
            'stretch' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Stack, $this>
     */
    public function stack(): BelongsTo
    {
        return $this->belongsTo(Stack::class);
    }

    /**
     * @return HasMany<ProjectMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('position');
    }

    /**
     * The roadmap modules the project puts into practice.
     *
     * @return BelongsToMany<Module, $this>
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class)->orderBy('modules.position');
    }
}
