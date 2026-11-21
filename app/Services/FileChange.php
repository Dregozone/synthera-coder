<?php

namespace App\Services;

/**
 * A change to one file in a target project, worked out by CodingTools: the
 * file's contents before (null for a new file) and after. Planning a change
 * leaves the disk alone, so the agent can propose it for approval; applying
 * it writes `after` to the file.
 */
final readonly class FileChange
{
    public function __construct(
        public string $path,
        public string $absolutePath,
        public ?string $before,
        public string $after,
    ) {}

    public function isNew(): bool
    {
        return $this->before === null;
    }
}
