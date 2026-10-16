<?php

use App\Jobs\RunAgentTurn;
use App\Models\AgentAction;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\CommandRun;
use App\Services\AgentActionExecutor;
use App\Services\AgentRunner;
use App\Services\BackgroundLauncher;
use App\Services\ChatService;
use App\Services\ContextService;
use App\Services\DiffService;
use App\Services\LmStudio;
use App\Services\ProjectDirectories;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Lottery;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Agentic Chat')] class extends Component
{
    private const int MESSAGE_BATCH_SIZE = 50;

    // Caps the automatic approve -> verify -> fix follow-up turns per user
    // request so a confused local model cannot loop indefinitely.
    private const int MAX_CONTINUATION_ROUNDS = 6;

    #[Url]
    public int $sessionId = 0;

    public int $visibleMessageCount = self::MESSAGE_BATCH_SIZE;

    public array $availableModels = [];

    public string $defaultModel = '';

    public string $currentModel = '';

    public array $availableChatTypes = [];

    public string $defaultChatType = '';

    public string $currentChatType = '';

    public string $sessionTitle = '';

    public int $numberOfMessages = 0;

    public string $prompt = '';

    public string $tempSelectedProject = '';

    public string $selectedProject = '';

    public array $availableProjects = [];

    // Approximate token accounting for the conversation actually sent to the
    // model, measured against the selected model's context window.
    public int $estimatedTokens = 0;

    public int $contextWindow = 8192;

    // Tracks whether a queued agent turn is in flight so the UI can poll and
    // show a working indicator until the assistant replies.
    public bool $awaitingResponse = false;

    public int $awaitingSinceId = 0;

    // Reachability of the local model server: unknown | warm | reachable | offline.
    public string $modelStatus = 'unknown';

    // Holds an agent turn that should run on the next request (inline mode), so
    // the user's message renders immediately before the model is prompted.
    public array $pendingTurn = [];

    // Open on a project from a link (`?project=wifone`, or a worktree as
    // `?project=wifone.worktrees/issue-12-x`), and a tighter layout for
    // embedding (`?embed=1`, as Life OS's desks do). Both optional.
    #[Url]
    public string $project = '';

    #[Url]
    public bool $embed = false;

    // The sidebar command running now (its output shown as it comes), or 0.
    public int $activeRunId = 0;

    #[Computed]
    public function messages(): Collection
    {
        return ChatMessage::query()
            ->select(['id', 'chat_session_id', 'type', 'by', 'content', 'created_at'])
            ->where('chat_session_id', $this->sessionId)
            ->latest('id')
            ->limit($this->visibleMessageCount)
            ->get()
            ->reverse()
            ->values();
    }

    #[Computed]
    public function hiddenMessageCount(): int
    {
        return max(0, $this->numberOfMessages - $this->messages->count());
    }

    #[Computed]
    public function pendingActions(): Collection
    {
        return AgentAction::query()
            ->where('chat_session_id', $this->sessionId)
            ->where('status', AgentAction::STATUS_PENDING)
            ->orderBy('id')
            ->get();
    }

    /**
     * Each proposed write's diff, split into numbered rows for the changes panel.
     *
     * @return array<int, array{added: int, removed: int, segments: list<array{folded: bool, rows: list<array{type: 'add'|'remove'|'context', old: int|null, new: int|null, text: string}>}>}>
     */
    #[Computed]
    public function pendingDiffs(): array
    {
        $diffs = app(DiffService::class);

        return $this->pendingActions
            ->where('type', AgentAction::TYPE_WRITE)
            ->mapWithKeys(fn (AgentAction $action): array => [
                $action->id => $diffs->rows((string) ($action->payload['diff'] ?? '')),
            ])
            ->all();
    }

    #[Computed]
    public function lastCommand(): ?AgentAction
    {
        return AgentAction::query()
            ->where('chat_session_id', $this->sessionId)
            ->where('type', AgentAction::TYPE_COMMAND)
            ->where('status', AgentAction::STATUS_EXECUTED)
            ->latest('id')
            ->first();
    }

    /**
     * The sidebar command running in this session now, or the one just started.
     */
    #[Computed]
    public function activeRun(): ?CommandRun
    {
        $run = CommandRun::query()
            ->where('chat_session_id', $this->sessionId)
            ->latest('id')
            ->first();

        return $run && ($run->isRunning() || $run->id === $this->activeRunId) ? $run : null;
    }

    /**
     * The sidebar's commands, by group (Composer, npm, Tests, Git).
     *
     * @return array<string, array<string, array{label: string, icon: string, command: string}>>
     */
    #[Computed]
    public function commandGroups(): array
    {
        $groups = [];

        foreach (config('synthera-coder.commands', []) as $key => $command) {
            $groups[$command['group']][$key] = $command;
        }

        return $groups;
    }

    #[Computed]
    public function recentSessions(): Collection
    {
        return ChatSession::query()
            ->select(['id', 'title', 'number_of_messages', 'created_at', 'updated_at'])
            ->latest('updated_at')
            ->limit(10)
            ->get();
    }

    public function mount(ChatService $chatService, ProjectDirectories $directories): void
    {
        $linked = $this->project !== '' ? $directories->find($this->project) : null;

        if (! $this->sessionId) {
            // Opened on a project: carry on with its latest chat rather than starting another.
            $this->sessionId = $linked !== null
                ? ($this->latestSessionFor($linked) ?? ChatService::nextSessionId())
                : ChatService::nextSessionId();
            $this->redirect(request()->fullUrlWithQuery(['sessionId' => $this->sessionId]));

            return;
        }

        // 1 in 10 full page loads performs light maintenance.
        Lottery::odds(1, 10)
            ->winner(fn () => $chatService->maintenanceTasks())
            ->choose();

        $this->availableModels = config('synthera-coder.chat_models', []);
        $this->defaultModel = config('synthera-coder.default_chat_model', '');
        $this->currentModel = $this->defaultModel;

        $this->availableChatTypes = config('synthera-coder.chat_types', []);
        $this->defaultChatType = config('synthera-coder.default_chat_type', '');
        $this->currentChatType = $this->defaultChatType;

        // Discover sibling projects (and their worktrees) to offer as working directories.
        $this->availableProjects = collect($directories->all())
            ->mapWithKeys(fn (array $project): array => [$project['label'] => $project['path']])
            ->toArray();

        $this->loadSessionData();

        // Opened on a project from a link: work in it.
        if ($linked !== null && ! ProjectDirectories::same($linked, $this->selectedProject)) {
            $this->selectedProject = $linked;
            $this->tempSelectedProject = $linked;

            ChatSession::query()
                ->whereKey($this->sessionId)
                ->update(['current_working_directory' => $linked]);
        }
    }

    /**
     * The most recently used chat that works in this folder, if any.
     */
    private function latestSessionFor(string $path): ?int
    {
        $id = ChatSession::query()
            ->whereNotNull('current_working_directory')
            ->latest('updated_at')
            ->limit(200)
            ->get(['id', 'current_working_directory'])
            ->first(fn (ChatSession $session): bool => ProjectDirectories::same($session->current_working_directory, $path))
            ?->id;

        return $id === null ? null : (int) $id;
    }

    private function loadSessionData(): void
    {
        $session = ChatSession::query()
            ->select(['id', 'title', 'number_of_messages', 'current_model', 'current_chat_type', 'current_working_directory'])
            ->find($this->sessionId);

        if (! $session) {
            return;
        }

        $this->currentModel = $session->current_model ?: $this->defaultModel;
        $this->currentChatType = $session->current_chat_type ?: $this->defaultChatType;
        $this->sessionTitle = $session->title ?? '';
        $this->numberOfMessages = $session->number_of_messages ?? 0;
        $this->selectedProject = $session->current_working_directory ?? '';
        $this->tempSelectedProject = $this->selectedProject;

        $models = config('synthera-coder.models', []);
        $this->contextWindow = (int) ($models[$this->currentModel]['context_window'] ?? 8192);
        $this->estimatedTokens = $this->estimateConversationTokens();
    }

    /**
     * Rough token estimate (≈4 characters per token) for the conversation that
     * would be sent to the model, accounting for any condensed summary.
     */
    private function estimateConversationTokens(): int
    {
        $context = $this->contextService();
        $summary = (string) $context->get('conversation_summary', '');

        // Only messages after the condense/clear cutoff are sent to the model.
        $cutoff = max(
            (int) $context->get('condensed_at_message_id', 0),
            (int) $context->get('history_reset_at_message_id', 0),
        );

        $characters = ChatMessage::query()
            ->where('chat_session_id', $this->sessionId)
            ->when($cutoff > 0, fn ($query) => $query->where('id', '>', $cutoff))
            ->sum(DB::raw('LENGTH(content)'));

        return (int) ceil(((int) $characters + strlen($summary)) / 4);
    }

    private function contextService(): ContextService
    {
        return new ContextService($this->sessionId);
    }

    private function syncSessionConfiguration(): void
    {
        ChatSession::query()
            ->whereKey($this->sessionId)
            ->update([
                'current_model' => $this->currentModel,
                'current_chat_type' => $this->currentChatType,
            ]);
    }

    /**
     * Mark that a queued agent turn has started so the UI polls until it replies.
     */
    private function beginAwaiting(): void
    {
        $this->awaitingSinceId = (int) (ChatMessage::query()
            ->where('chat_session_id', $this->sessionId)
            ->max('id') ?? 0);

        $this->awaitingResponse = true;
    }

    public function agentIsWorking(): bool
    {
        if (! $this->awaitingResponse) {
            return false;
        }

        $replied = ChatMessage::query()
            ->where('chat_session_id', $this->sessionId)
            ->where('by', 'assistant')
            ->where('id', '>', $this->awaitingSinceId)
            ->exists();

        return ! $replied;
    }

    public function poll(): void
    {
        $this->loadSessionData();

        if (! $this->agentIsWorking()) {
            $this->awaitingResponse = false;
        }
    }

    /**
     * Kick off an agent turn. Inline mode (the default) runs it in a follow-up
     * request triggered from the browser so the chat updates before the model
     * is prompted; queued mode pushes it to a worker.
     *
     * @param  array{kind: string, userMessageId?: int, feedback?: string}  $payload
     */
    private function startTurn(array $payload): void
    {
        $this->beginAwaiting();

        if (config('synthera-coder.run_turns_inline', true)) {
            $this->pendingTurn = $payload;
            $this->dispatch('process-agent-turn');

            return;
        }

        RunAgentTurn::dispatch(
            sessionId: $this->sessionId,
            kind: $payload['kind'],
            userMessageId: $payload['userMessageId'] ?? null,
            feedback: $payload['feedback'] ?? null,
        );
    }

    /**
     * Runs the queued turn inline (invoked by the browser after the send/approve
     * request has rendered). This is what keeps the app working under Herd with
     * no separate queue worker.
     */
    public function processPendingTurn(AgentRunner $runner): void
    {
        $payload = $this->pendingTurn;
        $this->pendingTurn = [];

        if ($payload === []) {
            return;
        }

        if (($payload['kind'] ?? null) === 'user' && isset($payload['userMessageId'])) {
            $runner->runForUserMessage($this->sessionId, (int) $payload['userMessageId']);
        } elseif (($payload['kind'] ?? null) === 'continue' && isset($payload['feedback'])) {
            $runner->continueAfterActions($this->sessionId, (string) $payload['feedback']);
        }

        $this->loadSessionData();
        $this->dispatch('scroll-to-bottom');
    }

    public function loadOlderMessages(): void
    {
        $this->visibleMessageCount += self::MESSAGE_BATCH_SIZE;
    }

    public function sendMessage(ChatService $chatService): void
    {
        $prompt = trim($this->prompt);

        if ($prompt === '') {
            return;
        }

        if (blank($this->selectedProject)) {
            $this->sendToast('Select a working directory before sending a request.', '', 'danger');

            return;
        }

        $this->syncSessionConfiguration();

        $userMessageId = $chatService->addMessage(
            sessionId: $this->sessionId,
            type: $this->currentChatType,
            by: 'user',
            content: $prompt,
        );

        $this->prompt = '';

        // A fresh user request resets the automatic follow-up budget.
        $this->contextService()->set('continuation_rounds', 0);

        $this->startTurn(['kind' => 'user', 'userMessageId' => $userMessageId]);

        $this->loadSessionData();
        $this->dispatch('scroll-to-bottom');
    }

    public function approveAction(AgentActionExecutor $executor, int $actionId): void
    {
        $action = AgentAction::query()
            ->where('chat_session_id', $this->sessionId)
            ->where('status', AgentAction::STATUS_PENDING)
            ->find($actionId);

        if (! $action) {
            return;
        }

        $result = $executor->execute($action);

        $this->addMessage(
            type: 'info',
            by: 'assistant',
            content: $this->actionResultSummary($action, $result),
        );

        $this->maybeContinueAfterActions();

        $this->dispatch('scroll-to-bottom');
    }

    public function rejectAction(int $actionId): void
    {
        $action = AgentAction::query()
            ->where('chat_session_id', $this->sessionId)
            ->where('status', AgentAction::STATUS_PENDING)
            ->find($actionId);

        if (! $action) {
            return;
        }

        $action->update(['status' => AgentAction::STATUS_REJECTED]);

        $this->addMessage(type: 'info', by: 'user', content: 'Rejected proposed '.$this->actionLabel($action).'.');

        $this->maybeContinueAfterActions();

        $this->dispatch('scroll-to-bottom');
    }

    /**
     * Turn down a proposed action with a note of what to change instead; the
     * note goes back to the model so it can propose a revised action.
     */
    public function requestChanges(int $actionId, string $note): void
    {
        $note = trim($note);

        if ($note === '') {
            $this->sendToast('Say what should change before sending it back.', '', 'warning');

            return;
        }

        $action = AgentAction::query()
            ->where('chat_session_id', $this->sessionId)
            ->where('status', AgentAction::STATUS_PENDING)
            ->find($actionId);

        if (! $action) {
            return;
        }

        $action->update([
            'status' => AgentAction::STATUS_REJECTED,
            'result' => $note,
        ]);

        $this->addMessage(type: 'info', by: 'user', content: 'Requested changes to the proposed '.$this->actionLabel($action).":\n\n".$note);

        $this->maybeContinueAfterActions();

        $this->dispatch('scroll-to-bottom');
    }

    private function actionLabel(AgentAction $action): string
    {
        return $action->type === AgentAction::TYPE_WRITE
            ? 'write to '.($action->payload['path'] ?? 'file')
            : "command '".($action->payload['command'] ?? '')."'";
    }

    public function approveAllActions(AgentActionExecutor $executor): void
    {
        foreach ($this->pendingActions as $action) {
            $result = $executor->execute($action);

            $this->addMessage(
                type: 'info',
                by: 'assistant',
                content: $this->actionResultSummary($action, $result),
            );
        }

        unset($this->pendingActions);

        $this->maybeContinueAfterActions();

        $this->dispatch('scroll-to-bottom');
    }

    private function actionResultSummary(AgentAction $action, string $result): string
    {
        if ($action->type === AgentAction::TYPE_WRITE) {
            return '**Applied write** to `'.($action->payload['path'] ?? '').'`.';
        }

        return "**Ran command** `".($action->payload['command'] ?? '')."`\n\n```\n".$result."\n```";
    }

    /**
     * Once every proposed action for the batch is resolved, feed the results
     * back to the agent so it can verify and continue.
     */
    private function maybeContinueAfterActions(): void
    {
        // Ensure we see the freshly-updated action statuses, not a cached read.
        unset($this->pendingActions);

        if ($this->pendingActions->isNotEmpty()) {
            return;
        }

        $cursor = (int) $this->contextService()->get('last_action_cursor', 0);

        $resolved = AgentAction::query()
            ->where('chat_session_id', $this->sessionId)
            ->where('id', '>', $cursor)
            ->where('status', '!=', AgentAction::STATUS_PENDING)
            ->orderBy('id')
            ->get();

        if ($resolved->isEmpty()) {
            return;
        }

        $lines = $resolved->map(function (AgentAction $action): string {
            // A rejection's result, when there is one, is the change the user asked for instead.
            if ($action->status === AgentAction::STATUS_REJECTED && filled($action->result)) {
                return ucfirst($this->actionLabel($action)).": rejected. The user asked for these changes instead:\n".$action->result;
            }

            if ($action->type === AgentAction::TYPE_WRITE) {
                return "Write to {$action->payload['path']}: {$action->status}.";
            }

            return "Command '{$action->payload['command']}': {$action->status}.\n".($action->result ?? '');
        })->implode("\n\n");

        $this->contextService()->set('last_action_cursor', (int) $resolved->max('id'));

        // Bound the automatic verify/fix follow-ups per user request.
        $rounds = (int) $this->contextService()->get('continuation_rounds', 0);

        if ($rounds >= self::MAX_CONTINUATION_ROUNDS) {
            $this->addMessage(
                type: 'info',
                by: 'assistant',
                content: 'Reached the automatic follow-up limit for this request. Review the results above and send a new message to continue.',
            );

            return;
        }

        $this->contextService()->set('continuation_rounds', $rounds + 1);

        $feedback = "The user reviewed the actions you proposed. Results:\n\n".$lines.
            "\n\nIf you changed any code, verify it by proposing to run the project's tests (for a Laravel project, 'php artisan test'). Then continue with the request or, if everything is done, give a final summary.";

        $this->startTurn(['kind' => 'continue', 'feedback' => $feedback]);
    }

    public function saveSessionTitle(): void
    {
        $session = ChatSession::find($this->sessionId);

        if ($session) {
            $session->update(['title' => $this->sessionTitle]);
        }

        $this->addMessage(type: 'info', by: 'user', content: 'Session title updated to: '.$this->sessionTitle);

        $this->loadSessionData();
    }

    public function addMessage(string $type, string $by, string $content): void
    {
        app(ChatService::class)->addMessage(
            sessionId: $this->sessionId,
            type: $type,
            by: $by,
            content: $content,
        );

        $this->loadSessionData();
    }

    public function sendToast(string $message, string $heading = '', string $type = 'success'): void
    {
        if ($heading !== '') {
            Flux::toast(text: $message, heading: $heading, variant: $type);
        } else {
            Flux::toast(text: $message, variant: $type);
        }
    }

    public function changeWorkingDirectory(): void
    {
        $this->selectedProject = $this->tempSelectedProject;
        // Chosen here: a project from the link no longer applies.
        $this->project = '';

        ChatSession::query()
            ->whereKey($this->sessionId)
            ->update(['current_working_directory' => $this->selectedProject]);

        $this->addMessage(type: 'info', by: 'user', content: 'Changed working directory to: '.$this->selectedProject);

        $this->dispatch('scroll-to-bottom');
    }

    /**
     * Run one of the sidebar's commands (by its key: only those listed in
     * config('synthera-coder.commands') can run) in the selected project. It
     * runs in its own process, so a long one isn't cut off; its output shows as
     * it comes, and the result is added to the chat when it ends.
     */
    public function runCommand(string $key, BackgroundLauncher $launcher): void
    {
        $command = config('synthera-coder.commands.'.$key);

        if (! is_array($command) || ! isset($command['command'])) {
            $this->sendToast('That is not one of the commands this page runs.', '', 'danger');

            return;
        }

        if (! is_dir($this->selectedProject)) {
            $this->sendToast('Invalid project directory: '.$this->selectedProject, '', 'danger');

            return;
        }

        if ($this->activeRun?->isRunning()) {
            $this->sendToast('Wait for '.$this->activeRun->command.' to finish first.', '', 'warning');

            return;
        }

        $run = CommandRun::create([
            'chat_session_id' => $this->sessionId,
            'command_key' => $key,
            'command' => $command['command'],
            'cwd' => $this->selectedProject,
            'status' => CommandRun::STATUS_RUNNING,
        ]);

        $this->activeRunId = $run->id;
        unset($this->activeRun);

        try {
            $launcher->artisan(['synthera:run-command', (string) $run->id]);
        } catch (\Throwable $e) {
            $run->update(['status' => CommandRun::STATUS_FAILED, 'output' => 'It could not be started: '.$e->getMessage(), 'finished_at' => now()]);
        }
    }

    /**
     * While a command runs: its latest output (and, once it ends, the chat).
     */
    public function pollRun(): void
    {
        unset($this->activeRun);

        if ($this->activeRun && ! $this->activeRun->isRunning()) {
            $this->loadSessionData();
            $this->dispatch('scroll-to-bottom');
        }
    }

    /**
     * Close the finished command's output.
     */
    public function dismissRun(): void
    {
        $this->activeRunId = 0;
        unset($this->activeRun);

        $this->dispatch('scroll-to-bottom');
    }

    public function condenseContext(AgentRunner $runner): void
    {
        $hasMessages = ChatMessage::query()->where('chat_session_id', $this->sessionId)->exists();

        $summary = $runner->condense($this->sessionId);

        $this->addMessage(
            type: 'info',
            by: 'assistant',
            content: match (true) {
                $summary !== '' => "**Context condensed.** Earlier messages will now be sent to the model as this summary:\n\n".$summary,
                $hasMessages => 'Could not condense the context — is the model loaded and reachable in LM Studio?',
                default => 'Nothing to condense yet.',
            },
        );

        $this->dispatch('scroll-to-bottom');
        $this->loadSessionData();
    }

    public function clearContext(): void
    {
        $maxId = (int) (ChatMessage::query()
            ->where('chat_session_id', $this->sessionId)
            ->max('id') ?? 0);

        // Start the model's working context fresh from here without deleting the
        // visible chat history.
        $context = $this->contextService();
        $context->set('conversation_summary', '');
        $context->set('condensed_at_message_id', 0);
        $context->set('history_reset_at_message_id', $maxId);

        $this->addMessage(
            type: 'info',
            by: 'user',
            content: 'Context cleared — the model will start fresh from here. Earlier messages stay visible above.',
        );

        $this->dispatch('scroll-to-bottom');
        $this->loadSessionData();
    }

    public function deleteSession(): void
    {
        $deletedId = $this->sessionId;

        ChatSession::whereKey($deletedId)->delete();
        \Illuminate\Support\Facades\File::deleteDirectory(ContextService::sessionDirectory($deletedId));

        $this->redirect(route('home'));
    }

    /**
     * Ping the local model server to surface warm/cold/offline status without
     * blocking normal page loads (invoked on demand from the sidebar).
     */
    public function refreshModelStatus(LmStudio $lmStudio): void
    {
        $this->modelStatus = $lmStudio->status($this->currentModelId());
    }

    /**
     * Unload the model now, freeing the GPU (it loads again when next asked).
     */
    public function unloadModel(LmStudio $lmStudio): void
    {
        $unloaded = $lmStudio->unload($this->currentModelId());

        $this->sendToast($unloaded ? 'Model unloaded: it loads again when next asked.' : 'LM Studio did not unload it (is it loaded, and is LM Studio running?)', '', $unloaded ? 'success' : 'warning');
        $this->modelStatus = $lmStudio->status($this->currentModelId());
    }

    private function currentModelId(): string
    {
        return (string) (config('synthera-coder.models', [])[$this->currentModel]['model'] ?? $this->currentModel);
    }
};
?>

<div
    x-data="{
        editTitle() {
            const newTitle = prompt('Enter a new title for this chat session:', '{{ $sessionTitle }}');

            if (newTitle !== null) {
                @this.sessionTitle = newTitle;
                @this.saveSessionTitle();
            }
        },

        async runCommand(key) {
            await $wire.runCommand(key);
        },
    }"
    id="container"
    class="w-full flex gap-4"
    @if ($this->agentIsWorking()) wire:poll.2s="poll" @elseif ($this->activeRun?->isRunning()) wire:poll.1s="pollRun" @endif
    x-on:process-agent-turn.window="$wire.processPendingTurn()"
    x-on:scroll-to-bottom.window="$nextTick(() => { $refs.messages.scrollTop = $refs.messages.scrollHeight })"
>
    @php($hasChanges = $this->pendingActions->isNotEmpty())

    {{-- Left side: the chat, narrowed to a third while there are changes to review --}}
    <section @class(['flex flex-col h-screen py-4', 'w-1/3' => $hasChanges, 'w-3/4' => ! $hasChanges])>
        {{-- Main output screen --}}
        <div
            x-ref="messages"
            x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })"
            class="grow ml-4 mb-4 p-4 border border-zinc-300 dark:border-zinc-700 rounded-2xl shadow-lg overflow-y-auto"
        >
            <div class="w-full">
                @if ($this->hiddenMessageCount > 0)
                    <div class="mb-4 flex justify-center">
                        <flux:button wire:click="loadOlderMessages" size="sm" variant="ghost">
                            Load {{ $this->hiddenMessageCount }} older {{ Str::plural('message', $this->hiddenMessageCount) }}
                        </flux:button>
                    </div>
                @endif

                @foreach ($this->messages as $message)
                    <div wire:key="message-{{ $message->id }}" class="flex w-full mb-3 {{ $message['type'] === 'info' ? 'justify-center' : ($message['by'] === 'user' ? 'justify-start' : 'justify-end') }}">

                        @if ($message['type'] === 'info')
                            <flux:callout icon="exclamation-circle" color="sky" inline class="w-full max-w-2xl">
                                <flux:callout.heading>
                                    <div class="chat-markdown">{!! Str::markdown($message['content'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                                    <flux:spacer />
                                    <div class="text-xs">{{ $message['created_at']->diffForHumans() }}</div>
                                </flux:callout.heading>
                            </flux:callout>

                        @elseif ($message['by'] === 'user')
                            <div class="flex items-start gap-3 max-w-[75%]">
                                <div class="flex-shrink-0 size-8 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center ring-1 ring-zinc-300 dark:ring-zinc-600">
                                    <flux:icon.user class="size-4 text-zinc-600 dark:text-zinc-300" />
                                </div>
                                <div class="flex flex-col gap-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">You</span>
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $message['created_at']->diffForHumans() }}</span>
                                    </div>
                                    <div class="bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-2xl rounded-tl-sm px-4 py-3">
                                        <p class="text-sm leading-relaxed text-zinc-800 dark:text-zinc-200 whitespace-pre-wrap break-words">{{ $message['content'] }}</p>
                                    </div>
                                </div>
                            </div>

                        @else
                            <div class="flex items-start gap-3 max-w-[75%] flex-row-reverse">
                                <div class="flex-shrink-0 size-8 rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center shadow-sm ring-1 ring-violet-400/30">
                                    <flux:icon.cpu-chip class="size-4 text-white" />
                                </div>
                                <div class="flex flex-col gap-1 items-end min-w-0 w-full">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $message['created_at']->diffForHumans() }}</span>
                                        <span class="text-xs font-semibold text-violet-600 dark:text-violet-400">Synthera</span>
                                    </div>
                                    <div class="w-full bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl rounded-tr-sm px-5 py-4 shadow-sm">
                                        <div class="chat-markdown text-zinc-800 dark:text-zinc-200">{!! Str::markdown($message['content'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                                    </div>
                                </div>
                            </div>
                        @endif

                    </div>
                @endforeach

                {{-- Working indicator (server-driven while a queued turn is in flight) --}}
                @if ($this->agentIsWorking())
                    <div class="flex w-full mb-3 justify-end">
                        <div class="flex items-start gap-3 max-w-[75%] flex-row-reverse animate-pulse">
                            <div class="flex-shrink-0 size-8 rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center shadow-sm ring-1 ring-violet-400/30">
                                <flux:icon.cpu-chip class="size-4 text-white" />
                            </div>
                            <div class="w-full bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl rounded-tr-sm px-5 py-4 shadow-sm">
                                <div class="text-zinc-500 dark:text-zinc-400 text-sm">Working locally… the model is thinking and using tools.</div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- A sidebar command's output, as it comes --}}
        @if ($run = $this->activeRun)
            <div class="ml-4 mb-3" data-test="command-run">
                <flux:card class="!p-3">
                    <div class="flex items-center gap-2 mb-2">
                        @if ($run->isRunning())
                            <flux:icon.arrow-path class="size-4 animate-spin text-sky-500" />
                            <flux:subheading>Running <code>{{ $run->command }}</code> in {{ basename($run->cwd) }}…</flux:subheading>
                        @else
                            <flux:badge size="sm" :color="$run->exit_code === 0 ? 'emerald' : 'rose'">exit {{ $run->exit_code ?? '?' }}</flux:badge>
                            <flux:subheading><code>{{ $run->command }}</code> finished: its output is in the chat.</flux:subheading>
                            <flux:button size="xs" variant="ghost" icon="x-mark" class="ml-auto" wire:click="dismissRun" />
                        @endif
                    </div>
                    <pre
                        x-data
                        x-effect="$el.scrollTop = $el.scrollHeight"
                        class="max-h-56 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-zinc-100 dark:bg-zinc-900 p-2 text-xs leading-snug"
                    >{{ $run->output ?: 'Starting…' }}</pre>
                </flux:card>
            </div>
        @endif

        <div class="ml-4">
            <form wire:submit="sendMessage" @class(['w-full' => $hasChanges, 'w-[60%] mx-auto' => ! $hasChanges])>
                <flux:composer wire:model="prompt" label="Prompt" label:sr-only placeholder="How can I help you today?">
                    <x-slot name="actionsLeading">
                        @include('pages.includes.composer-leading')
                        @include('pages.includes.command')
                    </x-slot>

                    <x-slot name="actionsTrailing" class="gap-2">
                        @include('pages.includes.composer-trailing')
                    </x-slot>
                </flux:composer>
            </form>
        </div>
    </section>

    {{-- Middle: the proposed changes, to review and accept, run, send back or reject --}}
    @if ($hasChanges)
        <section class="flex-1 min-w-0 h-screen py-4" data-test="changes-panel">
            @include('pages.includes.changes-panel', ['actions' => $this->pendingActions, 'diffs' => $this->pendingDiffs])
        </section>
    @endif

    {{-- Right side --}}
    <section class="w-1/4 h-screen flex flex-col border-l-2 border-zinc-300 dark:border-zinc-700">
        <div class="w-full my-4 px-4">
            <div class="flex items-center gap-2 mb-2">
                <flux:modal.trigger name="select-chat">
                    <flux:button size="sm" variant="ghost" icon="arrow-left" />
                </flux:modal.trigger>

                @include('pages.includes.select-chat', ['chatSessions' => $this->recentSessions])

                <flux:badge>#{{ $sessionId }}</flux:badge>
                <flux:heading level="1" size="xl">{{ $sessionTitle ?: 'New Chat Session' }}</flux:heading>

                <flux:button size="sm" variant="ghost" icon="pencil" x-on:click="editTitle()" class="ml-auto" />

                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="trash"
                    wire:click="deleteSession"
                    wire:confirm="Delete this chat session and all its messages? This cannot be undone."
                />

                <flux:button size="sm" variant="ghost" icon="plus" href="{{ request()->fullUrlWithQuery(['sessionId' => null]) }}" />
            </div>

            <flux:subheading>
                <flux:text>{{ $numberOfMessages }} {{ Str::plural('message', $numberOfMessages) }}</flux:text>
            </flux:subheading>
        </div>

        <flux:separator variant="subtle" class="mb-4" />

        <div class="flex flex-col grow overflow-y-auto">
            {{-- Working directory --}}
            <flux:card class="mx-2">
                <div class="flex justify-between items-center gap-2 mb-2">
                    <flux:subheading>Working Directory</flux:subheading>

                    <div class="flex items-center gap-2">
                        <flux:modal.trigger name="change-directory">
                            <div class="flex items-center gap-1 cursor-pointer">
                                <flux:icon.folder-open class="size-4 text-zinc-600 dark:text-zinc-300" />
                                <span class="text-sm text-zinc-700 dark:text-zinc-300 truncate max-w-[10rem]">{{ $selectedProject ? basename($selectedProject) : 'None' }}</span>
                            </div>
                        </flux:modal.trigger>

                        <flux:modal name="change-directory" class="md:w-96">
                            <div class="space-y-6">
                                <div>
                                    <flux:heading size="lg">Change Directory</flux:heading>
                                    <flux:text class="mt-2">Select a project to work on.</flux:text>
                                </div>

                                <flux:select wire:model="tempSelectedProject" label="Select a project" placeholder="Select a project">
                                    @foreach ($availableProjects as $project => $path)
                                        <flux:select.option :value="$path">{{ $project }}</flux:select.option>
                                    @endforeach
                                </flux:select>

                                <div class="flex">
                                    <flux:spacer />
                                    <flux:button type="submit" variant="primary" wire:click="changeWorkingDirectory()" x-on:click="$flux.modal('change-directory').close()">Switch project</flux:button>
                                </div>
                            </div>
                        </flux:modal>
                    </div>
                </div>

                {{-- Only these run: the page sends a command's key, never a command line (config/synthera-coder.php). --}}
                @php($running = (bool) $this->activeRun?->isRunning())
                @foreach ($this->commandGroups as $group => $commands)
                    <flux:subheading size="lg" class="mt-2">{{ $group }}:</flux:subheading>
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        @foreach ($commands as $key => $command)
                            <flux:button size="sm" variant="outline" :icon="$command['icon']" x-on:click="runCommand('{{ $key }}')" :disabled="$running" title="{{ $command['command'] }}" data-test="command-{{ $key }}">{{ $command['label'] }}</flux:button>
                        @endforeach
                    </div>
                @endforeach

                <div class="flex items-center justify-between mt-3">
                    <div class="flex items-center gap-2">
                        <flux:subheading>Model:</flux:subheading>
                        @php($statusMeta = [
                            'warm' => ['emerald', 'Loaded'],
                            'cold' => ['zinc', 'Not loaded'],
                            'reachable' => ['amber', 'Reachable'],
                            'offline' => ['rose', 'Offline'],
                            'unknown' => ['zinc', 'Unknown'],
                        ][$modelStatus] ?? ['zinc', 'Unknown'])
                        <flux:badge size="sm" :color="$statusMeta[0]" data-test="model-status">{{ $statusMeta[1] }}</flux:badge>
                    </div>
                    <div class="flex items-center gap-1">
                        @if ($modelStatus === 'warm')
                            <flux:button size="xs" variant="ghost" icon="power" wire:click="unloadModel" wire:loading.attr="disabled" data-test="unload-model">Unload now</flux:button>
                        @endif
                        <flux:button size="xs" variant="ghost" icon="arrow-path" wire:click="refreshModelStatus" wire:loading.attr="disabled">Check</flux:button>
                    </div>
                </div>
                @if (config('synthera-coder.model_ttl') > 0)
                    <flux:text size="sm" class="mt-1">Loads when asked; unloads after {{ (int) ceil(config('synthera-coder.model_ttl') / 60) }} idle {{ Str::plural('minute', (int) ceil(config('synthera-coder.model_ttl') / 60)) }}.</flux:text>
                @endif
            </flux:card>

            {{-- Last check result --}}
            @if ($this->lastCommand)
                @php($lastPassed = str_contains((string) $this->lastCommand->result, 'Exit code: 0'))
                <flux:card class="mx-2 mt-4">
                    <flux:subheading>Last Check</flux:subheading>
                    <div class="flex items-center gap-2 mt-1">
                        <flux:badge size="sm" :color="$lastPassed ? 'emerald' : 'rose'" :icon="$lastPassed ? 'check-circle' : 'x-circle'">
                            {{ $lastPassed ? 'Passed' : 'Failed' }}
                        </flux:badge>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400 truncate">{{ $this->lastCommand->payload['command'] ?? '' }}</span>
                    </div>
                </flux:card>
            @endif

            <flux:spacer />

            {{-- Context window + actions --}}
            <flux:card class="mx-2 mt-4">
                <flux:subheading>Context Window</flux:subheading>

                <flux:text>
                    ~{{ number_format($estimatedTokens) }} /
                    {{ number_format($contextWindow) }} tokens
                    ({{ $contextWindow > 0 ? round(100 * $estimatedTokens / $contextWindow, 1) : 0 }}%)
                </flux:text>

                <div class="flex gap-2 mt-2">
                    <flux:button size="sm" variant="outline" label="Condense Context" icon="arrows-pointing-in" wire:click="condenseContext()" />
                    <flux:button size="sm" variant="outline" label="Clear Context" icon="trash" wire:click="clearContext()" />
                </div>
            </flux:card>

            @unless ($embed)
            <flux:card class="rounded-none !px-6 !py-3 border-t-2 border-zinc-300 dark:border-zinc-700 mt-4">
                <flux:text class="w-full text-center text-xs text-zinc-500 dark:text-zinc-400">
                    Synthera Coder -
                    <flux:link href="https://glacialstudio.co.uk?ref=synthera-coder" target="_blank" rel="noreferrer" class="text-zinc-500 dark:text-zinc-400 hover:underline">Glacial Studio</flux:link>
                    &copy; {{ date('Y') }} - Version: 0.0.1
                </flux:text>
            </flux:card>
            @endunless
        </div>
    </section>
</div>
