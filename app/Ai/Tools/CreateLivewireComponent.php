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
 * Proposes a Livewire 4 single-file component; the user approves it first.
 */
class CreateLivewireComponent implements Tool
{
    use UsesCodingTools;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Propose a Livewire 4 single-file component: pages::users.index for a page (route to it with CreateRoute, type livewire) or post.create for a component. Leave contents empty to start from the stub.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->withCodingTools(fn (CodingTools $tools, ChatSession $session): string => $this->proposeChange(
            $session,
            $tools->planLivewireComponent((string) ($request['name'] ?? ''), $request['contents'] ?? null),
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'contents' => $schema->string()->description('Optional: the whole file (PHP class and Blade).'),
        ];
    }
}
