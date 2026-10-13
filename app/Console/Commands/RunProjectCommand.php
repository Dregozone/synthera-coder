<?php

namespace App\Console\Commands;

use App\Models\CommandRun;
use App\Services\ChatService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Runs one sidebar command (a CommandRun) in its project, in its own process:
 * the output is saved as it comes so the page can show it live, and when it
 * ends the result is added to the chat as before.
 */
class RunProjectCommand extends Command
{
    protected $signature = 'synthera:run-command {run : The command run\'s id}';

    protected $description = 'Run a project command from the sidebar (started in the background by the page)';

    /**
     * How much output is kept on the run (the end, where the summary is).
     */
    private const int KEEP_CHARACTERS = 200_000;

    /**
     * How much of it goes into the chat message.
     */
    private const int CHAT_CHARACTERS = 6000;

    public function handle(ChatService $chatService): int
    {
        $run = CommandRun::query()->find((int) $this->argument('run'));

        if (! $run || $run->status !== CommandRun::STATUS_RUNNING) {
            return self::FAILURE;
        }

        $output = '';
        $savedAt = 0.0;
        $save = function (bool $now = false) use ($run, &$output, &$savedAt): void {
            if ($now || microtime(true) - $savedAt > 0.5) {
                $run->forceFill(['output' => $this->tail($output, self::KEEP_CHARACTERS)])->save();
                $savedAt = microtime(true);
            }
        };

        $process = Process::fromShellCommandline($run->command, $run->cwd, timeout: (int) config('synthera-coder.command_timeout', 1800));

        try {
            $process->run(function (string $type, string $chunk) use (&$output, $save): void {
                $output .= $this->plain($chunk);
                $save();
            });
            $exitCode = (int) $process->getExitCode();
        } catch (ProcessTimedOutException) {
            $output .= "\n\nStopped: it ran longer than ".config('synthera-coder.command_timeout', 1800).' seconds.';
            $exitCode = 124;
        } catch (\Throwable $e) {
            $output .= "\n\nIt could not run: ".$e->getMessage();
            $exitCode = 1;
        }

        $run->forceFill([
            'output' => $this->tail($output, self::KEEP_CHARACTERS),
            'status' => $exitCode === 0 ? CommandRun::STATUS_DONE : CommandRun::STATUS_FAILED,
            'exit_code' => $exitCode,
            'finished_at' => now(),
        ])->save();

        $body = trim($output);
        $chatService->addMessage(
            sessionId: $run->chat_session_id,
            type: 'info',
            by: 'user',
            content: "**Ran** `{$run->command}` (exit {$exitCode})\n\n```\n".($body === '' ? '(no output)' : $this->tail($body, self::CHAT_CHARACTERS))."\n```",
        );

        return self::SUCCESS;
    }

    /**
     * Colours and cursor movements out, Windows line endings made plain.
     */
    private function plain(string $text): string
    {
        $text = preg_replace('/\e\[[0-9;?]*[A-Za-z]/', '', $text) ?? $text;

        return str_replace("\r\n", "\n", $text);
    }

    /**
     * The last `$max` characters (the end of the output is where the summary is).
     */
    private function tail(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? '…'.Str::substr($text, -$max) : $text;
    }
}
