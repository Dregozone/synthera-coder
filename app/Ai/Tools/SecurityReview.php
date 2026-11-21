<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\UsesCodingTools;
use App\Services\CodingTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lists risky code in the project (mass assignment from the request, raw SQL
 * built from variables, unescaped Blade, env() outside config, …) by file and
 * line: the starting point for a security review.
 */
class SecurityReview implements Tool
{
    use UsesCodingTools;

    /**
     * The most findings the model is shown.
     */
    private const int MAX_FINDINGS = 60;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Scan the project for risky code (mass assignment, raw SQL from variables, unescaped Blade, env() outside config, eval/exec) and list it by file:line. Read each finding before judging it.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->withCodingTools(function (CodingTools $tools): string {
            $findings = $tools->securityReview();

            if ($findings === []) {
                return 'No risky patterns found. Also review the routes (middleware, auth) and the git diff.';
            }

            return collect($findings)
                ->take(self::MAX_FINDINGS)
                ->map(fn (array $finding): string => "{$finding['path']}:{$finding['line']} {$finding['rule']}: {$finding['code']}")
                ->implode("\n")
                .(count($findings) > self::MAX_FINDINGS ? "\n… and ".(count($findings) - self::MAX_FINDINGS).' more.' : '');
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
