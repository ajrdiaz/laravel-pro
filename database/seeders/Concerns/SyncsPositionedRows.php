<?php

namespace Database\Seeders\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;

trait SyncsPositionedRows
{
    /**
     * Make the relation hold exactly these rows, numbered from 1 in the given order.
     *
     * Rows are matched by position, so running a seeder again updates content in place
     * (keeping ids, and with them anything that points at them) instead of recreating it.
     *
     * @param  HasMany<*, *>  $relation
     * @param  list<array<string, mixed>>  $rows
     */
    protected function syncRows(HasMany $relation, array $rows): void
    {
        foreach ($rows as $index => $row) {
            $relation->updateOrCreate(['position' => $index + 1], $row);
        }

        $relation->getRelated()->newQuery()
            ->where($relation->getForeignKeyName(), $relation->getParentKey())
            ->where('position', '>', count($rows))
            ->delete();
    }
}
