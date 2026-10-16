<?php

namespace App\Services;

class DiffService
{
    /**
     * Produce a compact, unified-style line diff between two strings. New files
     * (empty `$old`) are rendered entirely as additions.
     */
    public function unified(string $old, string $new, string $path = ''): string
    {
        $oldLines = $old === '' ? [] : explode("\n", str_replace("\r\n", "\n", $old));
        $newLines = $new === '' ? [] : explode("\n", str_replace("\r\n", "\n", $new));

        $header = $path !== '' ? "--- {$path}\n+++ {$path}\n" : '';

        if ($oldLines === []) {
            return $header.implode("\n", array_map(static fn (string $line): string => '+'.$line, $newLines));
        }

        $lcs = $this->longestCommonSubsequence($oldLines, $newLines);

        $diff = [];
        $i = 0;
        $j = 0;

        foreach ($lcs as $common) {
            while ($i < count($oldLines) && $oldLines[$i] !== $common) {
                $diff[] = '-'.$oldLines[$i];
                $i++;
            }

            while ($j < count($newLines) && $newLines[$j] !== $common) {
                $diff[] = '+'.$newLines[$j];
                $j++;
            }

            $diff[] = ' '.$common;
            $i++;
            $j++;
        }

        while ($i < count($oldLines)) {
            $diff[] = '-'.$oldLines[$i];
            $i++;
        }

        while ($j < count($newLines)) {
            $diff[] = '+'.$newLines[$j];
            $j++;
        }

        return $header.implode("\n", $diff);
    }

    /**
     * Split a diff from unified() into numbered rows for display, side by side
     * with each line's number in the old and new file. Runs of unchanged lines
     * more than `$context` lines from any change are folded into their own
     * segment, so a long file shows only what changed (as GitHub does).
     *
     * @return array{added: int, removed: int, segments: list<array{folded: bool, rows: list<array{type: 'add'|'remove'|'context', old: int|null, new: int|null, text: string}>}>}
     */
    public function rows(string $diff, int $context = 3): array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $diff));

        if (count($lines) >= 2 && str_starts_with($lines[0], '--- ') && str_starts_with($lines[1], '+++ ')) {
            $lines = array_slice($lines, 2);
        }

        $rows = [];
        $old = 0;
        $new = 0;
        $added = 0;
        $removed = 0;

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $text = substr($line, 1);

            if ($line[0] === '+') {
                $rows[] = ['type' => 'add', 'old' => null, 'new' => ++$new, 'text' => $text];
                $added++;
            } elseif ($line[0] === '-') {
                $rows[] = ['type' => 'remove', 'old' => ++$old, 'new' => null, 'text' => $text];
                $removed++;
            } else {
                $rows[] = ['type' => 'context', 'old' => ++$old, 'new' => ++$new, 'text' => $text];
            }
        }

        return ['added' => $added, 'removed' => $removed, 'segments' => $this->fold($rows, $context)];
    }

    /**
     * Pick a highlight.js language for a file from its name ('plaintext' when unknown).
     */
    public function language(string $path): string
    {
        $name = strtolower(basename($path));

        if (str_ends_with($name, '.blade.php')) {
            return 'php-template';
        }

        return match (pathinfo($name, PATHINFO_EXTENSION)) {
            'php' => 'php',
            'js', 'mjs', 'cjs', 'jsx' => 'javascript',
            'ts', 'tsx' => 'typescript',
            'json' => 'json',
            'css' => 'css',
            'scss' => 'scss',
            'html', 'htm', 'xml', 'svg', 'vue' => 'xml',
            'md', 'markdown' => 'markdown',
            'yml', 'yaml', 'neon' => 'yaml',
            'sh', 'bash' => 'bash',
            'sql' => 'sql',
            'env', 'ini' => 'ini',
            default => 'plaintext',
        };
    }

    /**
     * Group rows into segments, folding unchanged lines far from any change.
     *
     * @param  list<array{type: 'add'|'remove'|'context', old: int|null, new: int|null, text: string}>  $rows
     * @return list<array{folded: bool, rows: list<array{type: 'add'|'remove'|'context', old: int|null, new: int|null, text: string}>}>
     */
    private function fold(array $rows, int $context): array
    {
        $nearChange = [];

        foreach ($rows as $index => $row) {
            if ($row['type'] !== 'context') {
                for ($near = max(0, $index - $context); $near <= $index + $context; $near++) {
                    $nearChange[$near] = true;
                }
            }
        }

        $segments = [];

        foreach ($rows as $index => $row) {
            $folded = ! isset($nearChange[$index]);
            $last = array_key_last($segments);

            if ($last !== null && $segments[$last]['folded'] === $folded) {
                $segments[$last]['rows'][] = $row;
            } else {
                $segments[] = ['folded' => $folded, 'rows' => [$row]];
            }
        }

        // Folding a line or two saves nothing: show short runs as they are.
        foreach ($segments as $index => $segment) {
            if ($segment['folded'] && count($segment['rows']) <= 2) {
                $segments[$index]['folded'] = false;
            }
        }

        return $segments;
    }

    /**
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     * @return array<int, string>
     */
    private function longestCommonSubsequence(array $a, array $b): array
    {
        $lengths = [];

        for ($i = 0; $i <= count($a); $i++) {
            $lengths[$i] = array_fill(0, count($b) + 1, 0);
        }

        for ($i = count($a) - 1; $i >= 0; $i--) {
            for ($j = count($b) - 1; $j >= 0; $j--) {
                $lengths[$i][$j] = $a[$i] === $b[$j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        $result = [];
        $i = 0;
        $j = 0;

        while ($i < count($a) && $j < count($b)) {
            if ($a[$i] === $b[$j]) {
                $result[] = $a[$i];
                $i++;
                $j++;
            } elseif ($lengths[$i + 1][$j] >= $lengths[$i][$j + 1]) {
                $i++;
            } else {
                $j++;
            }
        }

        return $result;
    }
}
