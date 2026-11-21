<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\UsesCodingTools;
use App\Services\CodingTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * What a branch changes since it left the base branch (`git diff
 * main...feature-branch`), for reviewing work before it is merged.
 */
class GitDiff implements Tool
{
    use UsesCodingTools;

    /**
     * The most of a diff the model is shown (characters).
     */
    private const int MAX_CHARACTERS = 20000;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Show what a branch changes since it left the base branch (git diff base...branch), to review it. Defaults: base main (or master), branch the one checked out.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->withCodingTools(function (CodingTools $tools) use ($request): string {
            $diff = $tools->gitDiff($request['base'] ?? null, $request['branch'] ?? null);

            if (trim($diff) === '') {
                return 'No changes on this branch since it left the base branch.';
            }

            return Str::limit($diff, self::MAX_CHARACTERS, "\n… (diff cut short; ReadFile a changed file for the rest)");
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'base' => $schema->string()->description('Optional base branch, e.g. main'),
            'branch' => $schema->string()->description('Optional branch to review, e.g. feature-branch'),
        ];
    }
}
