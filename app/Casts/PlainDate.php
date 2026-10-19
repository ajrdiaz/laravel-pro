<?php

namespace App\Casts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

/**
 * A calendar date stored as a plain `Y-m-d` string.
 *
 * Laravel's own `date:Y-m-d` cast still writes a time part on SQLite, which breaks string
 * comparisons such as `where('due_on', '>', today()->toDateString())`.
 *
 * @implements CastsAttributes<CarbonInterface|null, mixed>
 */
class PlainDate implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonInterface
    {
        return $value === null ? null : Date::parse($value)->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Date::parse($value)->toDateString();
    }
}
