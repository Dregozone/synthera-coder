<?php

namespace App\Services;

class FileService
{
    /**
     * Resolve a caller-supplied path against the working directory and guarantee
     * the result stays inside it.
     *
     * Returns the absolute, normalised path when it is safe to use, or null when
     * there is no working directory, the working directory does not exist, or the
     * path escapes the working directory (via `..` traversal or an absolute path
     * pointing elsewhere). The target file itself need not exist yet, so write
     * tools can resolve paths for files they are about to create.
     */
    public function resolveFilePath(string $filePath, ?string $currentWorkingDirectory): ?string
    {
        if (blank($currentWorkingDirectory)) {
            return null;
        }

        $rootReal = realpath($currentWorkingDirectory);

        if ($rootReal === false) {
            return null;
        }

        $normalizedPath = preg_replace('/^\.[\/\\\\]/', '', $filePath) ?? $filePath;

        if ($this->isAbsolutePath($normalizedPath)) {
            $candidate = $normalizedPath;
        } else {
            $candidate = $rootReal.DIRECTORY_SEPARATOR.ltrim($normalizedPath, '/\\');
        }

        $rootNormalized = $this->lexicallyNormalize($rootReal);
        $candidateNormalized = $this->lexicallyNormalize($candidate);

        if (! $this->isWithin($rootNormalized, $candidateNormalized)) {
            return null;
        }

        return str_replace('/', DIRECTORY_SEPARATOR, $candidateNormalized);
    }

    public function isAbsolutePath(string $filePath): bool
    {
        return str_starts_with($filePath, '/')
            || str_starts_with($filePath, '\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $filePath) === 1;
    }

    /**
     * Collapse `.` and `..` segments lexically (without touching the filesystem)
     * and normalise separators to forward slashes.
     */
    private function lexicallyNormalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        $hasLeadingSlash = str_starts_with($path, '/');

        $segments = explode('/', $path);
        $resolved = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                $last = end($resolved);

                if ($resolved !== [] && $last !== '..' && preg_match('/^[A-Za-z]:$/', (string) $last) !== 1) {
                    array_pop($resolved);
                }

                continue;
            }

            $resolved[] = $segment;
        }

        $normalized = implode('/', $resolved);

        return $hasLeadingSlash ? '/'.$normalized : $normalized;
    }

    /**
     * Determine whether the candidate path is the root itself or nested inside it.
     * Comparison is case-insensitive on Windows where the filesystem is too.
     */
    private function isWithin(string $root, string $candidate): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $root = strtolower($root);
            $candidate = strtolower($candidate);
        }

        return $candidate === $root
            || str_starts_with($candidate, rtrim($root, '/').'/');
    }
}
