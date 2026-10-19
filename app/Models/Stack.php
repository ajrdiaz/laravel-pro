<?php

namespace App\Models;

use App\Casts\PlainDate;
use App\Enums\StackCategory;
use Carbon\CarbonInterface;
use Database\Factories\StackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A recommended technology stack for one type of application.
 *
 * @property int $id
 * @property string $slug
 * @property int $position
 * @property StackCategory $category
 * @property string $name
 * @property string $summary
 * @property list<string> $headline
 * @property list<string> $when_to_use
 * @property list<string> $when_to_avoid
 * @property list<string> $pros
 * @property list<string> $cons
 * @property list<string> $also_learn Topics the stack needs that the roadmap doesn't cover yet.
 * @property list<array{title: string, url: string}> $sources
 * @property CarbonInterface $reviewed_on When the content was last checked against its sources.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'position', 'category', 'name', 'summary', 'headline', 'when_to_use', 'when_to_avoid', 'pros', 'cons', 'also_learn', 'sources', 'reviewed_on'])]
class Stack extends Model
{
    /** @use HasFactory<StackFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => StackCategory::class,
            'headline' => 'array',
            'when_to_use' => 'array',
            'when_to_avoid' => 'array',
            'pros' => 'array',
            'cons' => 'array',
            'also_learn' => 'array',
            'sources' => 'array',
            'reviewed_on' => PlainDate::class,
        ];
    }

    /**
     * @return HasMany<StackTechnology, $this>
     */
    public function technologies(): HasMany
    {
        return $this->hasMany(StackTechnology::class)->orderBy('position');
    }

    /**
     * The roadmap modules worth mastering before building this kind of app.
     *
     * @return BelongsToMany<Module, $this>
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class)->orderBy('modules.position');
    }
}
