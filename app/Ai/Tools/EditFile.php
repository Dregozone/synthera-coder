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
 * Proposes replacing one passage of a file; the user approves it first.
 */
class EditFile implements Tool
{
    use UsesCodingTools;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Propose replacing one passage of a file: "search" must match exactly once (copy it from ReadFile), "replace" is the new text. The user approves it first.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->withCodingTools(fn (CodingTools $tools, ChatSession $session): string => $this->proposeChange(
            $session,
            $tools->planEditFile((string) ($request['path'] ?? ''), (string) ($request['search'] ?? ''), (string) ($request['replace'] ?? '')),
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->required(),
            'search' => $schema->string()->required(),
            'replace' => $schema->string()->required(),
        ];
    }
}
