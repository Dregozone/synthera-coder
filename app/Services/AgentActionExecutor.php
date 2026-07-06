<?php

namespace App\Services;

use App\Models\AgentAction;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Applies an approved AgentAction to disk (file write) or the shell (command),
 * recording the outcome back onto the action. This is the only place file
 * writes and command execution happen for agent-proposed changes.
 */
class AgentActionExecutor
{
    public function __construct(private readonly FileService $fileService) {}

    /**
     * Execute a pending action and return a human-readable result string.
     */
    public function execute(AgentAction $action): string
    {
        try {
            $result = $action->type === AgentAction::TYPE_WRITE
                ? $this->applyWrite($action)
                : $this->runCommand($action);

            $action->update([
                'status' => AgentAction::STATUS_EXECUTED,
                'result' => $result,
            ]);

            return $result;
        } catch (Throwable $e) {
            $action->update([
                'status' => AgentAction::STATUS_FAILED,
                'result' => $e->getMessage(),
            ]);

            return 'Failed: '.$e->getMessage();
        }
    }

    private function applyWrite(AgentAction $action): string
    {
        $cwd = $action->chatSession?->current_working_directory;

        // Defence in depth: re-check the target is still inside the working
        // directory at execution time, not just when it was proposed.
        $absolutePath = $this->fileService->resolveFilePath(
            filePath: (string) ($action->payload['path'] ?? ''),
            currentWorkingDirectory: $cwd,
        );

        if ($absolutePath === null) {
            throw new \RuntimeException('Refusing to write outside the working directory.');
        }

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, (string) ($action->payload['contents'] ?? ''));

        return 'Wrote '.($action->payload['path'] ?? $absolutePath);
    }

    private function runCommand(AgentAction $action): string
    {
        $cwd = $action->payload['cwd'] ?? $action->chatSession?->current_working_directory;

        if (! is_string($cwd) || ! is_dir($cwd)) {
            throw new \RuntimeException('Working directory is not valid: '.(string) $cwd);
        }

        $process = Process::fromShellCommandline((string) $action->payload['command'], $cwd);
        $process->setTimeout(300);
        $process->run();

        // Strip ANSI colour codes so results are clean for both the UI and the
        // model that receives them as feedback.
        $output = trim(preg_replace('/\e\[[0-9;]*m/', '', $process->getOutput()) ?? '');
        $error = trim(preg_replace('/\e\[[0-9;]*m/', '', $process->getErrorOutput()) ?? '');

        $result = "Exit code: {$process->getExitCode()}";

        if ($output !== '') {
            $result .= "\n\n".$output;
        }

        if ($error !== '') {
            $result .= "\n\n[stderr]\n".$error;
        }

        return $result;
    }
}
