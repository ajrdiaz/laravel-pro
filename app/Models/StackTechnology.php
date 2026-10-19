<?php

namespace App\Models;

use App\Enums\StackLayer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One technology a stack uses on one of its layers.
 *
 * @property int $id
 * @property int $stack_id
 * @property int $position
 * @property StackLayer $layer
 * @property string $name
 * @property string $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['stack_id', 'position', 'layer', 'name', 'note'])]
class StackTechnology extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'layer' => StackLayer::class,
        ];
    }

    /**
     * @return BelongsTo<Stack, $this>
     */
    public function stack(): BelongsTo
    {
        return $this->belongsTo(Stack::class);
    }
}
