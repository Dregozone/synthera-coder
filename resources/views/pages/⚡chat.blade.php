<?php

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\ChatService;
use App\Services\ContextService;
use App\Services\ToolService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Lottery;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\Process\Process;

new #[Title('Agentic Chat')] class extends Component
{
    private const int MESSAGE_BATCH_SIZE = 50;

    #[Url]
    public int $sessionId = 0;

    public array $context = [];

    public int $visibleMessageCount = self::MESSAGE_BATCH_SIZE;

    public array $availableModels = [];

    public string $defaultModel = '';

    public string $currentModel = '';

    public array $availableChatTypes = [];

    public string $defaultChatType = '';
    
    public string $currentChatType = '';

    public string $sessionTitle = '';

    public int $numberOfMessages = 0;

    public string $originalPrompt = '';

    public string $prompt = '';

    public array $tasks = [];

    public array $taskResults = [];

    public array $taskStatuses = [];

    public string $tempSelectedProject = '';

    public string $selectedProject = '';

    public array $availableProjects = [];

    public array $toolResults = [];

    public int $contextSizeBytes = 0;

    public int $maxContextSizeBytes = 10;
    
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
    public function recentSessions(): Collection
    {
        return ChatSession::query()
            ->select(['id', 'title', 'number_of_messages', 'created_at', 'updated_at'])
            ->latest('updated_at')
            ->limit(10)
            ->get();
    }

    public function mount(ChatService $chatService): void
    {
        // Ensure the user has a valid session ID to track their chat session. This allows them to leave and come back without losing their place.
        if (! $this->sessionId) {
            $this->sessionId = ChatService::nextSessionId();
            $this->redirect(request()->fullUrlWithQuery(['sessionId' => $this->sessionId]));

            return;
        }

        $this->maxContextSizeBytes = ContextService::MAX_CONTEXT_SIZE_BYTES;

        // First time the page loads actions
        // 1 in 10 full page loads (Not livewire re-renders) will perform these maintenance tasks
        Lottery::odds(1, 10)
            ->winner(function () use ($chatService): void {
                $chatService->maintenanceTasks();
            })
            ->choose();

        $this->availableModels = config('synthera-coder.chat_models', []);
        $this->defaultModel = config('synthera-coder.default_chat_model', '');
        $this->currentModel = $this->defaultModel;

        $this->availableChatTypes = config('synthera-coder.chat_types', []);
        $this->defaultChatType = config('synthera-coder.default_chat_type', '');
        $this->currentChatType = $this->defaultChatType;

        // Find the sibling projects
        $this->availableProjects = collect(glob(base_path() . '/../*', GLOB_ONLYDIR))
            ->sortBy(fn($path) => strtolower(basename($path)))
            ->map(function ($path) {
                return [
                    'name' => basename($path),
                    'id' => str_replace('synthera-coder/../', '', $path),
                ];
            })
            ->pluck('id', 'name')
            ->toArray();

        $this->loadSessionData();
    }

    private function loadSessionData(): void
    {
        $session = ChatSession::query()
            ->select(['id', 'title', 'number_of_messages', 'current_model', 'current_chat_type', 'current_working_directory'])
            ->find($this->sessionId);

        if ($session) {
            $this->currentModel = $session->current_model ?: $this->defaultModel;
            $this->currentChatType = $session->current_chat_type ?: $this->defaultChatType;
            $this->sessionTitle = $session->title ?? '';
            $this->numberOfMessages = $session->number_of_messages ?? 0;
            $this->selectedProject = $session->current_working_directory ?? '';

            $this->tempSelectedProject = $this->selectedProject; // For the modal form

            // Update the context
            $contextService = new ContextService($this->sessionId);
            $this->context = $contextService->getContext();
            $this->contextSizeBytes = $contextService->getContextSizeBytes();
        }
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

    public function loadOlderMessages(): void
    {
        $this->visibleMessageCount += self::MESSAGE_BATCH_SIZE;
    }

    public function saveSessionTitle(): void
    {
        $session = ChatSession::find($this->sessionId);

        if ($session) {
            $session->update(['title' => $this->sessionTitle]);
        }

        $this->addMessage(type: 'info', by: 'user', content: 'Session title updated to: ' . $this->sessionTitle);

        $this->loadSessionData();
    }

    /**
     * This is the way to manually add additional explanation messages outside of the normal chat flow. For example, 
     * if we want to add a message every time the user updates the session title. 
     * This keeps all messages in one place and ensures the message count is always accurate.
     */
    public function addMessage(string $type, string $by, string $content): void
    {
        $chatService = app(ChatService::class);

        $chatService->addMessage(
            sessionId: $this->sessionId,
            type: $type,
            by: $by,
            content: $content,
        );

        $this->loadSessionData();
    }

    public function sendMessage(ChatService $chatService): void
    {
        // Clear out the old tasks
        $this->tasks = [
            'Building task list...',
        ];

        $this->syncSessionConfiguration();

        $chatService->sendMessage(
            sessionId: $this->sessionId,
            type: $this->currentChatType,
            model: $this->currentModel,
            message: $this->originalPrompt,
        );

        $contextService = new ContextService($this->sessionId);
        $contextService->upsertInContext('originalMessage', $this->originalPrompt);

        $this->loadSessionData();

        $this->dispatch('scroll-to-bottom');
    }

    public function updateTasks(ChatService $chatService): void
    {
        $this->syncSessionConfiguration();

        $tasks = $chatService->updateTasks(
            sessionId: $this->sessionId,
            type: $this->currentChatType,
            model: $this->currentModel,
            message: $this->originalPrompt,
        );

        $this->tasks = $tasks;

        $contextService = new ContextService($this->sessionId);
        $contextService->upsertInContext('tasks', $tasks);

        $this->taskStatuses = [];
        foreach ($tasks as $index => $task) {
            $this->taskStatuses[$index + 1] = 'Pending';
        }

        $this->loadSessionData();

        $this->dispatch('scroll-to-bottom');
    }

    public function findAssistantResponse(ChatService $chatService): void
    {
        $this->syncSessionConfiguration();

        $response = $chatService->findAssistantResponse(
            sessionId: $this->sessionId,
            type: $this->currentChatType,
            model: $this->currentModel,
            message: $this->originalPrompt,
            originalPrompt: $this->originalPrompt,
            taskResults: $this->toolResults,
        );

        $contextService = new ContextService($this->sessionId);
        $contextService->addToContext('messages', $response);

        $this->loadSessionData();

        $this->dispatch('scroll-to-bottom');
    }

    public function runToolsForTask(ToolService $toolService, int $taskNum): void
    {
        $this->syncSessionConfiguration();

        $toolResults = $toolService->runToolsForTask(
            sessionId: $this->sessionId,
            type: $this->currentChatType,
            model: $this->currentModel,
            message: $this->originalPrompt,
            tasks: $this->tasks,
            task: $taskNum,
        );

        $this->toolResults[$taskNum] = $toolResults;

        $contextService = new ContextService($this->sessionId);
        $contextService->addToContext('toolResults', $toolResults);

        $this->loadSessionData();

        $this->dispatch('scroll-to-bottom');
    }

    public function workOnTask(ChatService $chatService, int $taskNum): void
    {
        $this->syncSessionConfiguration();

        $taskResults = $chatService->workOnTask(
            sessionId: $this->sessionId,
            type: $this->currentChatType,
            model: $this->currentModel,
            message: $this->originalPrompt,
            tasks: $this->tasks,
            task: $taskNum,
            toolResults: $this->toolResults[$taskNum] ?? [],
            taskResults: $this->taskResults ?? [],
        );

        $this->loadSessionData();

        $this->taskResults[$taskNum] = $taskResults;
        $this->taskStatuses[$taskNum] = 'Done';

        $contextService = new ContextService($this->sessionId);
        $contextService->addToContext('taskResults', $this->taskResults);

        $this->dispatch('scroll-to-bottom');
    }

    public function startOnTask(ChatService $chatService, int $taskNum): void
    {
        $this->taskStatuses[$taskNum] = 'In Progress';

        $this->addMessage(
            type: $this->currentChatType,
            by: 'assistant', 
            content: "Starting task $taskNum: " . ($this->tasks[$taskNum - 1] ?? 'Unknown Task')
        );

        $this->dispatch('scroll-to-bottom');
    }

    public function assignSessionTitle(ChatService $chatService): void
    {
        $this->syncSessionConfiguration();

        if ($this->sessionTitle !== null && $this->sessionTitle !== '') {
            return; // Dont update an already populated title
        }

        $title = $chatService->assignSessionTitle(
            sessionId: $this->sessionId,
            type: $this->currentChatType,
            model: $this->currentModel,
            message: $this->originalPrompt,
            tasks: $this->tasks,
        );

        if ($title) {
            $this->sessionTitle = $title;
            $this->saveSessionTitle();
        }
    }

    public function sendToast(string $message, string $heading = '', string $type = 'success'): void
    {
        if ($heading !== '') {
            Flux::toast(
                text: $message,
                heading: $heading,
                variant: $type,
            );
        } else {
            Flux::toast(
                text: $message,
                variant: $type,
            );
        }
    }

    public function changeWorkingDirectory(): void
    {
        $this->selectedProject = $this->tempSelectedProject;

        // Update the DB
        ChatSession::query()
            ->whereKey($this->sessionId)
            ->update([
                'current_working_directory' => $this->selectedProject,
            ]);

        $this->addMessage(
            type: 'info',
            by: 'user',
            content: 'Changed working directory to: ' . $this->selectedProject
        );

        $this->dispatch('scroll-to-bottom');
    }

    public function runCommand(string $command): void
    {
        if (! is_dir($this->selectedProject)) {
            $this->sendToast('Invalid project directory: ' . $this->selectedProject, '', 'danger');

            return;
        }

        $commandArr = explode(' ', $command);

        // This is a placeholder for running terminal commands from the UI, such as security audits or git commands.
        $process = new Process(
            $commandArr,
            $this->selectedProject,
        );
        $process->setTimeout(180);
        $process->run();

        $successMsg = trim($process->getOutput());
        $errorMsg = trim($process->getErrorOutput());

        // $passed = !(strlen($errorMsg) > 0);

        $this->addMessage(
            type: 'info',
            by: 'user',
            content: "Executed command: $command. Result: $successMsg $errorMsg"
        );

        $this->dispatch('scroll-to-bottom');
    }

    public function condenseContext()
    {
        $contextService = new ContextService($this->sessionId);
        $contextService->condenseContext();

        $this->addMessage(
            type: 'info',
            by: 'user',
            content: "Context condensed."
        );

        $this->dispatch('scroll-to-bottom');

        $this->loadSessionData(); // Reload the context to get the condensed version
    }

    public function clearContext()
    {
        $contextService = new ContextService($this->sessionId);
        $contextService->clearContext();

        $this->addMessage(
            type: 'info',
            by: 'user',
            content: "Context cleared."
        );

        $this->dispatch('scroll-to-bottom');

        $this->loadSessionData(); // Reload the context to reflect the cleared state
    }
};
?>

<div
    x-data="{
        thinking: false,

        thinkingMessage: 'Thinking...',

        defaultThinkingMessage: 'Thinking...',

        thinkingStartedAt: null,

        thinkingMessageTimer: null,

        selectedThinkingStages: [],

        thinkingStageBuckets: [
            { afterSeconds: 0, texts: ['Warming up the gears...', 'Preparing the brainwaves...', 'Getting the engine started...'] },
            { afterSeconds: 5, texts: ['Thinking...', 'Sizing things up...', 'Sketching the first pass...'] },
            { afterSeconds: 10, texts: ['Thinking harder...', 'Turning over the tricky bits...', 'Working through the moving parts...'] },
            { afterSeconds: 20, texts: ['Connecting the dots...', 'Following the breadcrumb trail...', 'Lining up the puzzle pieces...'] },
            { afterSeconds: 30, texts: ['Digging through the details...', 'Inspecting the deeper layers...', 'Pressure-testing the assumptions...'] },
            { afterSeconds: 45, texts: ['Running the deep pass...', 'Going full detective mode...', 'Sweeping for edge cases...'] },
            { afterSeconds: 60, texts: ['Reasoning at full tilt...', 'Operating at maximum overthink...', 'Still cooking, but we are close...'] },
        ],

        editTitle() {
            const newTitle = prompt('Enter a new title for this chat session:', '{{ $sessionTitle }}');

            if (newTitle !== null) {
                @this.sessionTitle = newTitle;
                @this.saveSessionTitle();
            }
        },

        async sendMessage() {
            if ($wire.prompt.trim() === '') return;

            // Reset the prompt input immediately (instant client-side feedback)
            $wire.originalPrompt = $wire.prompt;
            $wire.prompt = '';

            // Add the users message and wait for it to be persisted
            await $wire.sendMessage();

            // Based on the users message, derive a tasks list
            this.startThinking();
            await $wire.updateTasks();
            this.stopThinking();

            // Based on the task list and the original message, generate and apply a ChatSession title
            this.startThinking();
            await $wire.assignSessionTitle();
            this.stopThinking();

            // Abort here if 'Reframe your question'
            if ($wire.tasks[0] && $wire.tasks[0].toLowerCase().includes('reframe your question')) {
                return;
            }

            for (let i = 0; i < $wire.tasks.length; i++) {
                let taskNumber = i + 1;

                this.startThinking();
                await $wire.startOnTask(taskNumber);
                this.stopThinking();

                // Per task find the required tools to solve the task and run them to get the necessary information to complete the task
                this.startThinking();
                await $wire.runToolsForTask(taskNumber);
                this.stopThinking();

                this.startThinking();
                await $wire.workOnTask(taskNumber);
                this.stopThinking();
            }

            // Only then fetch the assistant response
            this.startThinking();
            await $wire.findAssistantResponse();
            this.stopThinking();
        },

        async runCommand(command) {
            await $wire.sendToast('Running command: ' + command, '', 'info');

            await $wire.runCommand(command);

            await $wire.sendToast('Finished running command: ' + command, '', 'success');
        },

        pickThinkingStages() {
            return this.thinkingStageBuckets.map((stage) => {
                const randomIndex = Math.floor(Math.random() * stage.texts.length);

                return {
                    afterSeconds: stage.afterSeconds,
                    text: stage.texts[randomIndex],
                };
            });
        },

        getThinkingMessage(elapsedSeconds) {
            return this.selectedThinkingStages.reduce((currentMessage, stage) => {
                if (elapsedSeconds >= stage.afterSeconds) {
                    return stage.text;
                }

                return currentMessage;
            }, this.defaultThinkingMessage);
        },

        updateThinkingMessage() {
            if (this.thinkingStartedAt === null) {
                this.thinkingMessage = this.defaultThinkingMessage;

                return;
            }

            const elapsedSeconds = Math.floor((Date.now() - this.thinkingStartedAt) / 1000);

            this.thinkingMessage = this.getThinkingMessage(elapsedSeconds);
        },

        startThinking() {
            this.stopThinking();

            this.selectedThinkingStages = this.pickThinkingStages();
            this.thinkingStartedAt = Date.now();
            this.updateThinkingMessage();
            this.thinkingMessageTimer = window.setInterval(() => this.updateThinkingMessage(), 1000);

            this.thinking = true;
        },

        stopThinking() {
            if (this.thinkingMessageTimer !== null) {
                window.clearInterval(this.thinkingMessageTimer);
                this.thinkingMessageTimer = null;
            }

            this.thinkingStartedAt = null;
            this.selectedThinkingStages = [];
            this.thinkingMessage = this.defaultThinkingMessage;
            this.thinking = false;
        }
    }"
    id="container" 
    class="w-full flex gap-4"
    x-on:scroll-to-bottom.window="$nextTick(() => { $refs.messages.scrollTop = $refs.messages.scrollHeight })"
>
    {{-- Left side --}}
    <section class="w-3/4 flex flex-col h-screen py-4">
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

                @foreach ($this->messages as $index => $message)
                    <div wire:key="message-{{ $message->id }}" class="flex w-full mb-3 {{ $message['type'] === 'info' ? 'justify-center' : ($message['by'] === 'user' ? 'justify-start' : 'justify-end') }}">

                        @if ($message['type'] === 'info')
                            <flux:callout 
                                icon="exclamation-circle"
                                color="sky" 
                                inline
                                class="w-full max-w-2xl"
                            >
                                @if (strpos($message['content'], 'Tasks list updated') === 0)
                                    @php
                                        // Extract the tasks from the message content
                                        $tasksList = str_replace('Tasks list updated|', '', $message['content']);
                                        $tasksArr = explode('|', $tasksList);
                                    @endphp
                                    <flux:callout.heading>
                                        <div class="flex flex-col gap-2">
                                            <flux:subheading>Tasks list updated</flux:subheading>

                                            <ol class="list-decimal list-inside text-sm">
                                                @foreach ($tasksArr as $task)
                                                    <li>{{ $task }}</li>
                                                @endforeach
                                            </ol>
                                        </div>
                                    </flux:callout.heading>
                                @else
                                    <flux:callout.heading>
                                        <div>{{ $message['content'] }}</div>
                                        <flux:spacer />
                                        <div>{{ $message['created_at']->diffForHumans() }}</div>
                                    </flux:callout.heading>
                                @endif
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

                {{-- Thinking message --}}
                <div x-show="thinking" x-cloak class="flex w-full mb-3 justify-end">
                    <div class="flex items-start gap-3 max-w-[75%] flex-row-reverse animate-pulse">
                        <div class="flex-shrink-0 size-8 rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center shadow-sm ring-1 ring-violet-400/30">
                            <flux:icon.cpu-chip class="size-4 text-white" />
                        </div>
                        <div class="flex flex-col gap-1 items-end min-w-0 w-full">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-zinc-400 dark:text-zinc-500">Just now</span>
                                <span class="text-xs font-semibold text-violet-600 dark:text-violet-400">Synthera</span>
                            </div>
                            <div class="w-full bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl rounded-tr-sm px-5 py-4 shadow-sm">
                                <div class="chat-markdown text-zinc-800 dark:text-zinc-200" x-text="thinkingMessage"></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="ml-4">
            <form x-on:submit.prevent="sendMessage()" class="w-[60%] mx-auto">
                <flux:composer 
                    wire:model="prompt" 
                    label="Prompt" 
                    label:sr-only placeholder="How can I help you today?"
                >
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

    {{-- Right side --}}
    <section class="w-1/4 h-screen flex flex-col border-l-2 border-zinc-300 dark:border-zinc-700">
        {{-- Heading/title --}}
        <div class="w-full my-4 px-4">
            <div class="flex items-center gap-2 mb-2">
                <flux:modal.trigger name="select-chat">
                    <flux:button 
                        size="sm" 
                        variant="ghost" 
                        icon="arrow-left"
                    />
                </flux:modal.trigger>

                @include('pages.includes.select-chat', ['chatSessions' => $this->recentSessions])

                <flux:badge>#{{ $sessionId }}</flux:badge>
                <flux:heading level="1" size="xl">
                    {{ $sessionTitle ?: 'New Chat Session' }}
                </flux:heading>

                <flux:button 
                    size="sm" 
                    variant="ghost" 
                    icon="pencil" 
                    x-on:click="editTitle()" 
                    class="ml-auto"
                />

                <flux:button 
                    size="sm" 
                    variant="ghost" 
                    icon="trash" 
                    x-on:click="$wire.sendToast('Delete functionality not implemented yet.', '', 'danger')" 
                />

                <flux:button 
                    size="sm" 
                    variant="ghost" 
                    icon="plus" 
                    {{-- x-on:click="$wire.sendToast('Create new chat.')"  --}}
                    href="{{ request()->fullUrlWithQuery(['sessionId' => null]) }}"
                />
            </div>

            <flux:subheading>
                <flux:text>{{ $numberOfMessages }} {{ Str::plural('message', $numberOfMessages) }}</flux:text>
            </flux:subheading>
        </div>

        <flux:separator variant="subtle" class="mb-4" />

        <div class="flex flex-col grow">
            {{-- Working directory --}}
            <flux:card class="mx-2">
                <div class="flex justify-between items-center gap-2 mb-2">
                    <flux:subheading>Working Directory</flux:subheading>

                    <div class="flex items-center gap-2 mt-2 mb-4">
                        <flux:modal.trigger name="change-directory">
                            <flux:icon.folder-open class="size-4 text-zinc-600 dark:text-zinc-300" />

                            <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $selectedProject }}</span>
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

                                    <flux:button 
                                        type="submit" 
                                        variant="primary" 
                                        wire:click="changeWorkingDirectory()"
                                        x-on:click="$flux.modal('change-directory').close()"    
                                    >Switch project</flux:button>
                                </div>
                            </div>
                        </flux:modal>

                    </div>
                </div>

                <div class="flex justify-between items-start gap-2 mt-2 mb-4">
                    <div class="w-2/3">
                        <flux:subheading size="lg">Security:</flux:subheading>

                        <div class="flex items-center gap-2 mb-2">
                            <flux:subheading>Composer:</flux:subheading>
                            
                            <flux:button 
                                size="sm" 
                                variant="outline" 
                                label="Run security audits" 
                                icon="shield-check" 
                                x-on:click="runCommand('composer audit')"
                            >Audit</flux:button>
                            
                            <flux:button 
                                size="sm" 
                                variant="outline" 
                                label="Run security audits" 
                                icon="wrench"
                                x-on:click="runCommand('composer audit fix')"
                            >Fix</flux:button>

                            <flux:button 
                                size="sm" 
                                variant="outline" 
                                label="Run security audits" 
                                icon="chevron-double-up"
                                x-on:click="runCommand('composer update')"
                            >Update</flux:button>
                        </div>

                        <div class="flex items-center gap-2 mb-2">
                            <flux:subheading>npm:</flux:subheading>
                            
                            <flux:button 
                                size="sm" 
                                variant="outline" 
                                label="Run security audits" 
                                icon="shield-check" 
                                x-on:click="runCommand('npm audit')"
                            >Audit</flux:button>
                            
                            <flux:button 
                                size="sm" 
                                variant="outline" 
                                label="Run security audits" 
                                icon="wrench"
                                x-on:click="runCommand('npm audit fix')"
                            >Fix</flux:button>

                            <flux:button 
                                size="sm" 
                                variant="outline" 
                                label="Run security audits" 
                                icon="chevron-double-up"
                                x-on:click="runCommand('npm update')"
                            >Update</flux:button>
                        </div>

                        <flux:subheading size="lg" class="mt-4">Git:</flux:subheading>
                        <div class="flex items-center gap-2 mb-2">
                            <flux:button size="sm" icon="arrow-down" x-on:click="runCommand('git pull')">Pull</flux:button>
                            <flux:button size="sm" icon="arrow-right">Commit</flux:button> {{-- suggested conventional commits in modal? on modal confirmation, git add . && git commit -m "(suggested commit message)" --}}
                            <flux:button size="sm" icon="arrow-up" x-on:click="runCommand('git push')">Push</flux:button>
                        </div>
                        <div>
                            (List most recent 2 branches worked on)
                            <br />- [switch] <u><strong>main</strong></u> {{-- underline the current branch, make bold too? --}}
                            <br />- [switch] feature/some-feature
                        </div>
                    </div>

                    <div class="w-1/3">
                        <flux:subheading size="lg">Laravel Details:</flux:subheading>
                        <ul>
                            <li>Laravel v13.x.x</li>
                            <li>Livewire v4.x.x</li>
                            <li>Flux Pro v2.x.x</li>
                            <li>Project size: ? MB</li>
                        </ul>

                        <div class="mt-4">
                            <flux:subheading size="lg">Tests:</flux:subheading>
                            <ul>
                                <li>x/y passing (last attempt)</li>
                            </ul>

                            <flux:button>Run tests</flux:button>
                        </div>
                    </div>
                </div>
            </flux:card>

            <flux:spacer />

            {{-- Context window + actions --}}
            <flux:card class="mx-2">
                <flux:subheading>Context Window</flux:subheading>

                <flux:text>
                    Context: 
                    {{ number_format($contextSizeBytes) }}b / 
                    {{ number_format($maxContextSizeBytes) }}b 
                    ({{ ROUND(100 * $contextSizeBytes / $maxContextSizeBytes, 1) }}%)
                </flux:text>

                <flux:subheading>Context Actions</flux:subheading>

                <flux:button 
                    size="sm" 
                    variant="outline" 
                    label="Condense Context" 
                    icon="arrows-pointing-in" 
                    wire:click="condenseContext()"
                />

                <flux:button 
                    size="sm" 
                    variant="outline" 
                    label="Clear Context" 
                    icon="trash" 
                    wire:click="clearContext()"
                />
            </flux:card>


            <flux:spacer />

            {{-- Tasks --}}
            <flux:card class="mx-2">
                <flux:subheading>Tasks</flux:subheading>

                <ol class="mt-2 list-decimal list-inside text-sm text-zinc-700 dark:text-zinc-300">
                    @forelse ($tasks as $index => $task)
                        <li
                            wire:key="task-{{ $index + 1 }}"
                            @class([
                                'line-through' => isset($taskStatuses[$index + 1]) && $taskStatuses[$index + 1] == 'Done',
                                'text-zinc-500 dark:text-zinc-400' => ! isset($taskStatuses[$index + 1]) || $taskStatuses[$index + 1] != 'Done',
                            ])
                        >
                            {{ $task }}
                        </li>
                    @empty
                        <div class="italic text-zinc-500 dark:text-zinc-400">Awaiting tasks...</div>
                    @endforelse
                </ol> 
            </flux:card>

            <flux:spacer />
            <flux:spacer />

            {{-- Agents hot/cold: We want to show which agent is currently warmed up, cold starts can delay the response 30s - 1min --}}
            <flux:card class="mx-2">
                <flux:subheading>Agents Cold Start Status</flux:subheading>

                @foreach ($availableModels as $model)
                    <div>
                        [Status] {{ $model }}
                    </div>
                @endforeach
            </flux:card>

            <flux:card class="rounded-none !px-6 !py-3 border-t-2 border-zinc-300 dark:border-zinc-700 mt-4">
                <flux:text class="w-full text-center text-xs text-zinc-500 dark:text-zinc-400">
                    Synthera Coder - 
                    
                    <flux:link
                        href="https://glacialstudio.co.uk?ref=synthera-coder"
                        target="_blank"
                        rel="noreferrer"
                        class="text-zinc-500 dark:text-zinc-400 hover:underline"
                    >
                        Glacial Studio
                    </flux:link> 
                    
                    &copy; {{ date('Y') }} - Version: 0.0.1
                </flux:text>
            </flux:card>
        </div>
    </section>
</div>
