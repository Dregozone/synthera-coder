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
 * Proposes an artisan command class; the user approves it first.
 */
class CreateArtisanCommand implements Tool
{
    use UsesCodingTools;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Propose an artisan command in app/Console/Commands, e.g. signature "do:something {userId}". "handle" is the body of handle(): int (return self::SUCCESS or self::FAILURE).';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->withCodingTools(fn (CodingTools $tools, ChatSession $session): string => $this->proposeChange($session, $tools->planArtisanCommand(
            signature: (string) ($request['signature'] ?? ''),
            description: (string) ($request['description'] ?? ''),
            handle: (string) ($request['handle'] ?? ''),
            imports: self::listFrom($request['imports'] ?? ''),
        )));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'signature' => $schema->string()->required(),
            'description' => $schema->string()->required(),
            'handle' => $schema->string()->required(),
            'imports' => $schema->string()->description('Optional, comma-separated classes to import, e.g. App\Models\User'),
        ];
    }
}
