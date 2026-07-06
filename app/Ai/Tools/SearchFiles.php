<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesSessionContext;
use App\Services\FileService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Stringable;

class SearchFiles implements Tool
{
    use ResolvesSessionContext;

    private const int MAX_MATCHES = 50;

    private const MAX_FILE_SIZE = 512 * 1024;

    private const array SKIP_DIRECTORIES = ['vendor', 'node_modules', '.git', 'storage', '.idea', 'dist', 'build'];

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Search project files for a text substring; returns "path:line: text".';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = (string) ($request['query'] ?? '');

        if (trim($query) === '') {
            return 'Provide a non-empty search query.';
        }

        $path = trim((string) ($request['path'] ?? '.'));

        if ($path === '') {
            $path = '.';
        }

        $root = (new FileService)->resolveFilePath(
            filePath: $path,
            currentWorkingDirectory: $this->workingDirectory(),
        );

        if ($root === null) {
            return 'Path is outside the working directory, or no working directory is set.';
        }

        if (! is_dir($root)) {
            return "Not a directory: $path";
        }

        $matches = [];
        $needle = mb_strtolower($query);
        $rootLength = strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getSize() > self::MAX_FILE_SIZE) {
                continue;
            }

            if ($this->isInSkippedDirectory($file->getPathname())) {
                continue;
            }

            $contents = @file_get_contents($file->getPathname());

            if ($contents === false || $this->looksBinary($contents)) {
                continue;
            }

            $relative = substr($file->getPathname(), $rootLength);

            foreach (explode("\n", $contents) as $number => $line) {
                if (str_contains(mb_strtolower($line), $needle)) {
                    $matches[] = $relative.':'.($number + 1).': '.trim($line);

                    if (count($matches) >= self::MAX_MATCHES) {
                        return implode("\n", $matches)."\n… (results truncated at ".self::MAX_MATCHES.' matches)';
                    }
                }
            }
        }

        return $matches === [] ? "No matches found for: $query" : implode("\n", $matches);
    }

    private function isInSkippedDirectory(string $pathname): bool
    {
        $normalized = str_replace('\\', '/', $pathname);

        foreach (self::SKIP_DIRECTORIES as $directory) {
            if (str_contains($normalized, '/'.$directory.'/')) {
                return true;
            }
        }

        return false;
    }

    private function looksBinary(string $contents): bool
    {
        return str_contains(substr($contents, 0, 1024), "\0");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
            'path' => $schema->string(),
        ];
    }
}
