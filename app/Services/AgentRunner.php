<?php

namespace App\Services;

use App\Ai\Agents\LocalAgent;
use App\Ai\Tools\FindComposerVersion;
use App\Ai\Tools\ListDirectory;
use App\Ai\Tools\ReadFile;
use App\Ai\Tools\ReadPackageJson;
use App\Ai\Tools\RunCommand;
use App\Ai\Tools\SearchFiles;
use App\Ai\Tools\WriteFile;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\Message;
use Throwable;

/**
 * Runs a single agent turn: builds a runtime-configured LocalAgent (system
 * prompt + conversation history + sandboxed tools), prompts the local model,
 * and persists the assistant's reply. Mutating tools invoked during the turn
 * record pending AgentActions that the user approves separately.
 */
class AgentRunner
{
    private const int HISTORY_LIMIT = 30;

    public function __construct(private readonly ChatService $chatService) {}

    /**
     * Run a turn triggered by a user chat message.
     */
    public function runForUserMessage(int $sessionId, int $userMessageId): void
    {
        $userMessage = ChatMessage::find($userMessageId);

        if ($userMessage === null) {
            return;
        }

        $this->runTurn(
            sessionId: $sessionId,
            prompt: $userMessage->content,
            historyBeforeId: $userMessageId,
            assignTitleFrom: $userMessage->content,
        );
    }

    /**
     * Run a continuation turn after approved actions have been executed, feeding
     * their results back to the model.
     */
    public function continueAfterActions(int $sessionId, string $feedback): void
    {
        $this->runTurn(
            sessionId: $sessionId,
            prompt: $feedback,
            historyBeforeId: null,
            assignTitleFrom: null,
        );
    }

    private function runTurn(int $sessionId, string $prompt, ?int $historyBeforeId, ?string $assignTitleFrom): void
    {
        $session = ChatSession::find($sessionId);

        if ($session === null) {
            return;
        }

        if (blank($session->current_working_directory)) {
            $this->chatService->addMessage(
                sessionId: $sessionId,
                type: 'info',
                by: 'assistant',
                content: 'Select a working directory before sending a request.',
            );

            return;
        }

        [$provider, $model, $timeout] = $this->resolveModel($session);

        $agent = new LocalAgent(
            provider: $provider,
            model: $model,
            timeout: $timeout,
            systemInstructions: $this->systemPrompt($session),
            conversation: $this->history($sessionId, $historyBeforeId),
            availableTools: $this->tools($sessionId),
        );

        try {
            $text = (string) $agent->ask($prompt)->text;
        } catch (Throwable $e) {
            $this->chatService->addMessage(
                sessionId: $sessionId,
                type: 'info',
                by: 'assistant',
                content: 'The local model could not complete the request: '.$e->getMessage(),
            );

            return;
        }

        $this->chatService->addMessage(
            sessionId: $sessionId,
            type: $session->current_chat_type ?: 'agent',
            by: 'assistant',
            content: $text !== '' ? $text : '(The model returned an empty response.)',
        );

        if ($assignTitleFrom !== null && blank($session->title)) {
            $this->assignTitle($session, $assignTitleFrom);
        }
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    private function resolveModel(ChatSession $session): array
    {
        $models = config('synthera-coder.models', []);
        $label = $session->current_model ?: config('synthera-coder.default_chat_model');
        $config = $models[$label] ?? reset($models) ?: [];

        return [
            (string) ($config['provider'] ?? 'lmstudio'),
            (string) ($config['model'] ?? $label),
            (int) ($config['timeout'] ?? 300),
        ];
    }

    private function systemPrompt(ChatSession $session): string
    {
        $mode = $session->current_chat_type ?: 'agent';
        $tree = $this->workingDirectorySummary($session->current_working_directory);

        return <<<PROMPT
        You are Synthera Coder, a careful coding agent working on a local project, most often a Laravel application.

        Current mode: {$mode}.
        Working directory: {$session->current_working_directory}
        Top-level contents:
        {$tree}

        How you work:
        - Use the read-only tools (ReadFile, ListDirectory, SearchFiles, ReadPackageJson, FindComposerVersion) to inspect the project before proposing changes. These run immediately.
        - To change a file, call WriteFile with the FULL new file contents. To run a shell command (such as "php artisan test" or "vendor/bin/pint"), call RunCommand.
        - WriteFile and RunCommand do NOT take effect immediately: they record a proposal that the user must approve. After proposing, assume it will be applied and continue.
        - Keep changes minimal and focused. Prefer the smallest set of edits that satisfies the request, and follow the conventions already present in the project.
        - When you have proposed everything needed for this turn, stop and give a short summary of what you did and what you are proposing.
        PROMPT;
    }

    private function workingDirectorySummary(?string $directory): string
    {
        if (! is_string($directory) || ! is_dir($directory)) {
            return '(unavailable)';
        }

        $directories = collect(File::directories($directory))
            ->map(fn (string $dir): string => basename($dir).'/');

        $files = collect(File::files($directory))
            ->map(fn ($file): string => $file->getFilename());

        $entries = $directories->sort()->merge($files->sort())->take(40);

        return $entries->isEmpty() ? '(empty)' : $entries->implode(', ');
    }

    /**
     * Build the conversation history as AI SDK messages. When the context has
     * been condensed, older messages are replaced by a single summary message
     * and only messages after the condense point are included verbatim.
     *
     * @return array<int, Message>
     */
    private function history(int $sessionId, ?int $beforeId): array
    {
        $context = new ContextService($sessionId);
        $summary = (string) $context->get('conversation_summary', '');
        $condensedAt = (int) $context->get('condensed_at_message_id', 0);

        $messages = ChatMessage::query()
            ->where('chat_session_id', $sessionId)
            ->when($condensedAt > 0, fn ($query) => $query->where('id', '>', $condensedAt))
            ->when($beforeId !== null, fn ($query) => $query->where('id', '<', $beforeId))
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $message): Message => new Message(
                $message->by === 'user' ? 'user' : 'assistant',
                $message->type === 'info' ? '[system] '.$message->content : $message->content,
            ))
            ->values()
            ->all();

        if ($summary !== '') {
            array_unshift($messages, new Message('user', '[summary of earlier conversation] '.$summary));
        }

        return $messages;
    }

    /**
     * Summarise the conversation into a compact brief, store it, and mark the
     * point up to which messages have been condensed. Returns the summary.
     */
    public function condense(int $sessionId): string
    {
        $session = ChatSession::find($sessionId);

        if ($session === null) {
            return '';
        }

        $messages = ChatMessage::query()
            ->where('chat_session_id', $sessionId)
            ->orderBy('id')
            ->get();

        if ($messages->isEmpty()) {
            return '';
        }

        $transcript = $messages
            ->map(fn (ChatMessage $message): string => strtoupper($message->by).': '.$message->content)
            ->implode("\n\n");

        [$provider, $model, $timeout] = $this->resolveModel($session);

        try {
            $summary = (string) (new LocalAgent(provider: $provider, model: $model, timeout: $timeout))
                ->ask('Summarise the following conversation between a user and a coding agent into a concise brief (under 200 words) that captures decisions made, files changed, and any open tasks:'.PHP_EOL.PHP_EOL.$transcript)
                ->text;
        } catch (Throwable) {
            return '';
        }

        if (trim($summary) === '') {
            return '';
        }

        $context = new ContextService($sessionId);
        $context->set('conversation_summary', $summary);
        $context->set('condensed_at_message_id', (int) $messages->max('id'));

        return $summary;
    }

    /**
     * @return array<int, object>
     */
    private function tools(int $sessionId): array
    {
        return [
            new ReadFile($sessionId),
            new ListDirectory($sessionId),
            new SearchFiles($sessionId),
            new ReadPackageJson($sessionId),
            new FindComposerVersion($sessionId),
            new WriteFile($sessionId),
            new RunCommand($sessionId),
        ];
    }

    private function assignTitle(ChatSession $session, string $fromMessage): void
    {
        $title = Str::of($fromMessage)->trim()->limit(45)->value();

        try {
            [$provider, $model, $timeout] = $this->resolveModel($session);

            $generated = (new LocalAgent(provider: $provider, model: $model, timeout: $timeout))
                ->ask('Generate a concise, descriptive title (max 5 words) for a chat that starts with this message. Return only the title, no quotes:'.PHP_EOL.$fromMessage)
                ->text;

            if (trim((string) $generated) !== '') {
                $title = Str::of($generated)->trim()->limit(60)->value();
            }
        } catch (Throwable) {
            // Fall back to the truncated first message.
        }

        $session->update(['title' => $title]);
    }
}
