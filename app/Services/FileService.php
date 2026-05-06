<?php

namespace App\Services;

class FileService
{
    public function resolveFilePath(string $filePath, ?string $currentWorkingDirectory): ?string
    {
        $normalizedPath = preg_replace('/^\.[\/\\\\]/', '', $filePath) ?? $filePath;

        if ($this->isAbsolutePath($normalizedPath)) {
            return $normalizedPath;
        }

        if (blank($currentWorkingDirectory)) {
            return null;
        }

        return rtrim($currentWorkingDirectory, '/\\').DIRECTORY_SEPARATOR.ltrim($normalizedPath, '/\\');
    }

    public function isAbsolutePath(string $filePath): bool
    {
        return str_starts_with($filePath, '/')
            || str_starts_with($filePath, '\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $filePath) === 1;
    }
}
