<?php

namespace App\Services\Jobs;

/**
 * A skill found in a job posting.
 */
readonly class SkillMatch
{
    public function __construct(
        public string $name,
        /** The roadmap module that teaches it, or null when the roadmap has none. */
        public ?string $module,
        public int $mentions,
        /** Why the module only partly covers the skill. */
        public ?string $note,
        /** For skills outside the roadmap, the area they belong to. */
        public ?string $category,
        public ?string $hint,
    ) {}
}
