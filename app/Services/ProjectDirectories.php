<?php

namespace App\Services;

/**
 * The projects beside Synthera Coder (the folders in its parent directory),
 * and their git worktrees (`<project>.worktrees/<branch>`, as tools such as
 * Life OS make them for an issue), by a name usable in a link (`?project=`).
 */
class ProjectDirectories
{
    public function __construct(private readonly ?string $root = null) {}

    /**
     * Every project, by name: `wifone`, or `wifone.worktrees/issue-12-dark-mode`
     * for a worktree. Each has a label to show and its folder.
     *
     * @return array<string, array{label: string, path: string}>
     */
    public function all(): array
    {
        $root = $this->root();
        $self = realpath(base_path());
        $found = [];

        foreach (glob($root.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);

            if (str_ends_with($name, '.worktrees')) {
                $project = substr($name, 0, -strlen('.worktrees'));

                foreach (glob($dir.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [] as $tree) {
                    $found[$name.'/'.basename($tree)] = ['label' => $project.' › '.basename($tree), 'path' => $tree];
                }

                continue;
            }

            if (realpath($dir) === $self) {
                continue;
            }

            $found[$name] = ['label' => $name, 'path' => $dir];
        }

        uksort($found, fn (string $a, string $b): int => strnatcasecmp($found[$a]['label'], $found[$b]['label']));

        return $found;
    }

    /**
     * A project's folder by its name, or null if there is no such project.
     */
    public function find(string $name): ?string
    {
        return $this->all()[str_replace('\\', '/', trim($name))]['path'] ?? null;
    }

    /**
     * Whether two paths are the same folder (however they were written).
     */
    public static function same(?string $a, ?string $b): bool
    {
        if (blank($a) || blank($b)) {
            return false;
        }

        $normal = fn (string $path): string => strtolower(rtrim(str_replace('\\', '/', realpath($path) ?: $path), '/'));

        return $normal($a) === $normal($b);
    }

    private function root(): string
    {
        return rtrim($this->root ?? (realpath(dirname(base_path())) ?: dirname(base_path())), '\\/');
    }
}
