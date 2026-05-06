<?php

namespace App\Ai\Tools;

use App\Models\ChatSession;
use App\Services\FileService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class FindComposerVersion implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'This tool can be used to find the version of composer packages that are installed in the project.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $fileService = new FileService;

        $filePath = $fileService->resolveFilePath(
            filePath: 'composer.lock',
            currentWorkingDirectory: ChatSession::query()->latest('updated_at')->value('current_working_directory'),
        );

        if ($filePath === null) {
            return 'No working directory is set for relative file paths.';
        }

        if (! File::exists($filePath)) {
            return "File not found: $filePath";
        }

        $contents = File::get($filePath);

        $contentsArr = json_decode($contents, true);

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
