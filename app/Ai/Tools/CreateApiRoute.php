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
 * Proposes a route in routes/api.php; the user approves it first.
 */
class CreateApiRoute implements Tool
{
    use UsesCodingTools;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Propose an API route in routes/api.php (served under /api). "action" is a closure body (type closure) or Controller@method (type controller).';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->withCodingTools(fn (CodingTools $tools, ChatSession $session): string => $this->proposeChange($session, $tools->planApiRoute(
            method: (string) ($request['method'] ?? 'get'),
            uri: (string) ($request['path'] ?? ''),
            action: (string) ($request['action'] ?? ''),
            type: (string) ($request['type'] ?? CodingTools::ROUTE_CLOSURE),
            middleware: self::listFrom($request['middleware'] ?? ''),
            prefix: $request['prefix'] ?? null,
            name: $request['name'] ?? null,
        )));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'method' => $schema->string()->required()->enum(CodingTools::API_METHODS),
            'path' => $schema->string()->required()->description('The URI without /api, e.g. /status'),
            'action' => $schema->string()->required(),
            'type' => $schema->string()->enum([CodingTools::ROUTE_CLOSURE, CodingTools::ROUTE_CONTROLLER]),
            'middleware' => $schema->string()->description('Optional, comma-separated, e.g. auth:sanctum'),
            'prefix' => $schema->string()->description('Optional URI prefix for the group'),
            'name' => $schema->string()->description('Optional route name'),
        ];
    }
}
