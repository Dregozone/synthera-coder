<?php

namespace App\Ai\Tools\Concerns;

use App\Models\AgentAction;
use App\Models\ChatSession;
use App\Services\CodingTools;
use App\Services\DiffService;
use App\Services\FileChange;
use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * For tools that sit on CodingTools: run a task against the session's project
 * and hand the model its result, or what was wrong with its input; and turn a
 * planned FileChange into a pending write the user approves.
 *
 * Consuming classes must expose a nullable `$chatSessionId` property.
 */
trait UsesCodingTools
{
    use ResolvesSessionContext;

    /**
     * @param  Closure(CodingTools, ChatSession): string  $task
     */
    protected function withCodingTools(Closure $task): string
    {
        $session = $this->currentSession();

        if ($session === null || blank($session->current_working_directory)) {
            return 'No working directory is set for this session.';
        }

        try {
            return $task(new CodingTools($session->current_working_directory), $session);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Record a planned change as a pending write, with its diff, for the user to approve.
     */
    protected function proposeChange(ChatSession $session, FileChange $change): string
    {
        $action = AgentAction::create([
            'chat_session_id' => $session->id,
            'type' => AgentAction::TYPE_WRITE,
            'status' => AgentAction::STATUS_PENDING,
            'payload' => [
                'path' => $change->path,
                'absolute_path' => $change->absolutePath,
                'contents' => $change->after,
                'previous' => $change->before,
                'diff' => (new DiffService)->unified($change->before ?? '', $change->after, $change->path),
                'is_new' => $change->isNew(),
            ],
        ]);

        return "Proposed a change to {$change->path} as action #{$action->id}, awaiting user approval. Assume it will be applied and continue.";
    }

    /**
     * Record a command (a configured check) as pending, for the user to approve.
     */
    protected function proposeCommand(ChatSession $session, string $command): string
    {
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

    /**
     * A comma-separated list from the model, as a list.
     *
     * @return list<string>
     */
    protected static function listFrom(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(fn (mixed $item): string => trim((string) $item), $items), fn (string $item): bool => $item !== ''));
    }

    protected static function asJson(mixed $value): string
    {
        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
