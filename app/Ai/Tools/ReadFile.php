<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesSessionContext;
use App\Services\FileService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ReadFile implements Tool
{
    use ResolvesSessionContext;

    public function __construct(protected ?int $chatSessionId = null) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Check whether a file exists and read its contents. Provide a path relative to the working directory.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $filePath = (new FileService)->resolveFilePath(
            filePath: (string) $request['value'],
            currentWorkingDirectory: $this->workingDirectory(),
        );

        if ($filePath === null) {
            return 'Path is outside the working directory, or no working directory is set.';
        }

        if (! File::exists($filePath)) {
            return "File not found: $filePath";
        }

        return File::get($filePath);
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
