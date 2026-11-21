<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\UsesCodingTools;
use App\Models\ChatSession;
use App\Services\CodingTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Proposes running one of the configured quality checks (pint, rector,
 * phpstan, tests, routes) in the project; the user approves it first. The
 * model names a check, never a command line.
 */
class RunProjectCheck implements Tool
{
    use UsesCodingTools;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Propose running a project check: '.implode(', ', array_keys((array) config('synthera-coder.quality_checks', []))).'. The user approves it; its output comes back to you so you can fix what fails.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->withCodingTools(fn (CodingTools $tools, ChatSession $session): string => $this->proposeCommand(
            $session,
            $tools->checkCommand((string) ($request['check'] ?? '')),
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'check' => $schema->string()->required()->enum(array_keys((array) config('synthera-coder.quality_checks', []))),
        ];
    }
}
