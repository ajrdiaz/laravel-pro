<?php

namespace App\Models;

use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A tutorial written for one roadmap module. Its Markdown source lives in
 * database/data/lessons and is loaded by LessonSeeder.
 *
 * @property int $id
 * @property int $module_id
 * @property string $slug
 * @property int $position
 * @property string $title
 * @property string $summary
 * @property int $minutes Estimated reading time.
 * @property string $body Markdown.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['module_id', 'slug', 'position', 'title', 'summary', 'minutes', 'body'])]
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory;

    /**
     * Render the body to HTML. Raw HTML in the Markdown is stripped and unsafe links
     * (such as `javascript:`) are dropped, so the result is safe to inject into the page.
     */
    public function html(): string
    {
        return Str::markdown($this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
