<?php

namespace App\Models;

use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The user's own notes on a module, as plain text. A module has at most one note per user,
 * and a note with nothing in it does not exist.
 *
 * @property int $id
 * @property int $user_id
 * @property int $module_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'module_id', 'body'])]
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    /** The most characters a note can hold. */
    public const int MAX_LENGTH = 20000;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
