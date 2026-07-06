<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesSessionContext;
use App\Services\FileService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ReadPackageJson implements Tool
{
    use ResolvesSessionContext;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Read package.json (optionally one package name for just its version).';
    }

    public function handle(Request $request): Stringable|string
    {
        $filePath = (new FileService)->resolveFilePath(
            filePath: 'package.json',
            currentWorkingDirectory: $this->workingDirectory(),
        );

        if ($filePath === null) {
            return 'Path is outside the working directory, or no working directory is set.';
        }

        if (! File::exists($filePath)) {
            return "File not found: $filePath";
        }

        $data = json_decode(File::get($filePath), true);

        if (! is_array($data)) {
            return 'package.json could not be parsed.';
        }

        $dependencies = array_merge(
            is_array($data['dependencies'] ?? null) ? $data['dependencies'] : [],
            is_array($data['devDependencies'] ?? null) ? $data['devDependencies'] : [],
        );

        $package = trim((string) ($request['package'] ?? ''));

        if ($package !== '') {
            return $dependencies[$package] ?? "Package not found in package.json: $package";
        }

        return (string) json_encode([
            'dependencies' => $data['dependencies'] ?? new \stdClass,
            'devDependencies' => $data['devDependencies'] ?? new \stdClass,
            'scripts' => $data['scripts'] ?? new \stdClass,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'package' => $schema->string(),
        ];
    }
}
