<?php

namespace App\Services;

use Illuminate\Contracts\Support\Arrayable;

/**
 * How much of one module a user has completed.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class ModuleProgress implements Arrayable
{
    /** From this percentage on, a module counts as something the user already has. */
    public const int READY_PERCENTAGE = 70;

    public function __construct(
        public int $id,
        public string $slug,
        public int $position,
        public string $section,
        public string $command,
        public string $title,
        public int $done,
        public int $total,
    ) {}

    public function percentage(): int
    {
        return $this->total > 0 ? (int) round($this->done * 100 / $this->total) : 0;
    }

    public function isReady(): bool
    {
        return $this->percentage() >= self::READY_PERCENTAGE;
    }

    /**
     * @return array{id: int, slug: string, position: int, section: string, command: string, title: string, progress: array{done: int, total: int, pct: int}}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'position' => $this->position,
            'section' => $this->section,
            'command' => $this->command,
            'title' => $this->title,
            'progress' => [
                'done' => $this->done,
                'total' => $this->total,
                'pct' => $this->percentage(),
            ],
        ];
    }
}
