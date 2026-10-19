<?php

namespace App\Models;

use App\Enums\ResourceKind;
use App\Enums\ResourceLanguage;
use Database\Factories\LearningResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A curated external resource (docs page, course, video, article, book) for a module.
 *
 * @property int $id
 * @property int $module_id
 * @property int $position
 * @property string $title
 * @property string $url
 * @property string $provider
 * @property ResourceKind $kind
 * @property ResourceLanguage $language
 * @property bool $is_free
 * @property string $note Why it is worth the time.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['module_id', 'position', 'title', 'url', 'provider', 'kind', 'language', 'is_free', 'note'])]
class LearningResource extends Model
{
    /** @use HasFactory<LearningResourceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ResourceKind::class,
            'language' => ResourceLanguage::class,
            'is_free' => 'boolean',
        ];
    }

    /**
     * The privacy-friendly YouTube embed URL when this is a YouTube video, otherwise null.
     */
    public function embedUrl(): ?string
    {
        if ($this->kind !== ResourceKind::Video) {
            return null;
        }

        $parts = parse_url($this->url);
        $host = strtolower(preg_replace('/^www\./', '', $parts['host'] ?? '') ?? '');

        $id = match (true) {
            $host === 'youtu.be' => ltrim($parts['path'] ?? '', '/'),
            in_array($host, ['youtube.com', 'm.youtube.com'], true) => $this->queryValue($parts['query'] ?? '', 'v'),
            default => null,
        };

        return is_string($id) && preg_match('/^[\w-]{11}$/', $id)
            ? "https://www.youtube-nocookie.com/embed/{$id}"
            : null;
    }

    private function queryValue(string $query, string $key): ?string
    {
        parse_str($query, $values);

        return is_string($values[$key] ?? null) ? $values[$key] : null;
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
