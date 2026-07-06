<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesSessionContext;
use App\Services\FileService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class FindComposerVersion implements Tool
{
    use ResolvesSessionContext;

    public function __construct(protected ?int $chatSessionId = null) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get an installed Composer package version (from composer.lock).';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $filePath = (new FileService)->resolveFilePath(
            filePath: 'composer.lock',
            currentWorkingDirectory: $this->workingDirectory(),
        );

        if ($filePath === null) {
            return 'Path is outside the working directory, or no working directory is set.';
        }

        if (! File::exists($filePath)) {
            return "File not found: $filePath";
        }

        $contentsArr = json_decode(File::get($filePath), true);

        if (! isset($contentsArr['packages']) || ! is_array($contentsArr['packages'])) {
            return 'No packages found in composer.lock';
        }

        $versionNumber = collect($contentsArr['packages'])
            ->where('name', (string) $request['value'])
            ->pluck('version')
            ->first();

        return $versionNumber ?? "Package not found in composer.lock: {$request['value']}";
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
