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
 * Proposes a new file; the user approves it before it is written.
 */
class CreateFile implements Tool
{
    use UsesCodingTools;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Propose creating a NEW file with its contents (it must not exist yet; use EditFile to change one). The user approves it first.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->withCodingTools(fn (CodingTools $tools, ChatSession $session): string => $this->proposeChange(
            $session,
            $tools->planCreateFile((string) ($request['path'] ?? ''), (string) ($request['contents'] ?? '')),
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
