<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesSessionContext;
use App\Models\AgentAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Proposes a shell command to run in the working directory. This tool never
 * executes anything itself: it records a pending AgentAction that the user must
 * approve before the orchestrator runs it.
 */
class RunCommand implements Tool
{
    use ResolvesSessionContext;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Propose a shell command to run in the project working directory (e.g. "php artisan test"). The command is shown to the user and only executed after they approve it.';
    }

    public function handle(Request $request): Stringable|string
    {
        $session = $this->currentSession();

        if ($session === null) {
            return 'No working directory is set for this session.';
        }

        $command = trim((string) ($request['command'] ?? ''));

        if ($command === '') {
            return 'Provide a command to run.';
        }

        $action = AgentAction::create([
            'chat_session_id' => $session->id,
            'type' => AgentAction::TYPE_COMMAND,
            'status' => AgentAction::STATUS_PENDING,
            'payload' => [
                'command' => $command,
                'cwd' => $session->current_working_directory,
            ],
        ]);

        return "Proposed command '{$command}' as action #{$action->id}, awaiting user approval. Assume it will run and continue.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'command' => $schema->string()->required(),
        ];
    }
}
