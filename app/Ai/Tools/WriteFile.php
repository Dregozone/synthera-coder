<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesSessionContext;
use App\Models\AgentAction;
use App\Services\DiffService;
use App\Services\FileService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
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
    use ResolvesSessionContext;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Propose creating or overwriting a file with the given contents (path relative to the working directory). The change is shown to the user as a diff and only applied after they approve it.';
    }

    public function handle(Request $request): Stringable|string
    {
        $session = $this->currentSession();

        if ($session === null) {
            return 'No working directory is set for this session.';
        }

        $relativePath = (string) ($request['path'] ?? '');

        if (trim($relativePath) === '') {
            return 'Provide a file path to write to.';
        }

        $absolutePath = (new FileService)->resolveFilePath(
            filePath: $relativePath,
            currentWorkingDirectory: $session->current_working_directory,
        );

        if ($absolutePath === null) {
            return 'Path is outside the working directory, or no working directory is set.';
        }

        $newContents = (string) ($request['contents'] ?? '');
        $previousContents = File::exists($absolutePath) ? File::get($absolutePath) : null;

        $action = AgentAction::create([
            'chat_session_id' => $session->id,
            'type' => AgentAction::TYPE_WRITE,
            'status' => AgentAction::STATUS_PENDING,
            'payload' => [
                'path' => $relativePath,
                'absolute_path' => $absolutePath,
                'contents' => $newContents,
                'previous' => $previousContents,
                'diff' => (new DiffService)->unified($previousContents ?? '', $newContents, $relativePath),
                'is_new' => $previousContents === null,
            ],
        ]);

        return "Proposed write to {$relativePath} as action #{$action->id}, awaiting user approval. Assume it will be applied and continue.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->required(),
            'contents' => $schema->string()->required(),
        ];
    }
}
