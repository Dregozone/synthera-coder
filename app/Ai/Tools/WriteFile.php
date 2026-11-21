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
 * Proposes a file write. This tool never touches disk directly: it records a
 * pending AgentAction (with a diff) that the user must approve before the change
 * is applied by the orchestrator.
 */
class WriteFile implements Tool
{
    use UsesCodingTools;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Propose creating/overwriting a file with full new contents (user approves the diff before it applies).';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->withCodingTools(fn (CodingTools $tools, ChatSession $session): string => $this->proposeChange(
            $session,
            $tools->planWriteFile((string) ($request['path'] ?? ''), (string) ($request['contents'] ?? '')),
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->required(),
            'contents' => $schema->string()->required(),
        ];
    }
}
