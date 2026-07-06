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
