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
 * Proposes a GET route in routes/web.php; the user approves it first.
 */
class CreateRoute implements Tool
{
    use UsesCodingTools;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Propose a GET route in routes/web.php. "action" is a closure body (type closure), a Livewire page like pages::users.index (type livewire) or Controller@method (type controller). With middleware or a prefix it joins the group that has exactly those, or starts one.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->withCodingTools(fn (CodingTools $tools, ChatSession $session): string => $this->proposeChange($session, $tools->planRoute(
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
            'path' => $schema->string()->required()->description('The URI, e.g. /testing'),
            'action' => $schema->string()->required(),
            'type' => $schema->string()->enum([CodingTools::ROUTE_CLOSURE, CodingTools::ROUTE_LIVEWIRE, CodingTools::ROUTE_CONTROLLER]),
            'middleware' => $schema->string()->description('Optional, comma-separated, e.g. auth, verified'),
            'prefix' => $schema->string()->description('Optional URI prefix for the group, e.g. admin'),
            'name' => $schema->string()->description('Optional route name'),
        ];
    }
}
