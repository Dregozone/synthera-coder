<?php

namespace App\Ai\Tools;

use App\Models\ChatSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ReadFile implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'This tool can be used to check if a file exists or read the contents of a file.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $filePath = $this->resolveFilePath(
            filePath: (string) $request['value'],
            currentWorkingDirectory: ChatSession::query()->latest('updated_at')->value('current_working_directory'),
        );

        if ($filePath === null) {
            return 'No working directory is set for relative file paths.';
        }

        if (! File::exists($filePath)) {
            return "File not found: $filePath";
        }

        return File::get($filePath);
    }

    private function resolveFilePath(string $filePath, ?string $currentWorkingDirectory): ?string
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

    private function isAbsolutePath(string $filePath): bool
    {
        return str_starts_with($filePath, '/')
            || str_starts_with($filePath, '\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $filePath) === 1;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'value' => $schema->string()->required(),
        ];
    }
}
