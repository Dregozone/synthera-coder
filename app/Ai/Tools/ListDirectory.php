<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesSessionContext;
use App\Services\FileService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListDirectory implements Tool
{
    use ResolvesSessionContext;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'List the files and sub-directories directly inside a directory (relative to the working directory). Use "." for the project root.';
    }

    public function handle(Request $request): Stringable|string
    {
        $path = trim((string) ($request['path'] ?? '.'));

        if ($path === '') {
            $path = '.';
        }

        $resolved = (new FileService)->resolveFilePath(
            filePath: $path,
            currentWorkingDirectory: $this->workingDirectory(),
        );

        if ($resolved === null) {
            return 'Path is outside the working directory, or no working directory is set.';
        }

        if (! File::isDirectory($resolved)) {
            return "Not a directory: $path";
        }

        $directories = collect(File::directories($resolved))
            ->map(fn (string $dir): string => basename($dir).'/');

        $files = collect(File::files($resolved))
            ->map(fn ($file): string => $file->getFilename());

        $entries = $directories->sort()->merge($files->sort())->values();

        if ($entries->isEmpty()) {
            return "Directory is empty: $path";
        }

        return $entries->implode("\n");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string(),
        ];
    }
}
