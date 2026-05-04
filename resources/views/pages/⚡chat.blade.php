<?php

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\ChatService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Lottery;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Agentic Chat')] class extends Component
{
    private const int MESSAGE_BATCH_SIZE = 50;

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

    public string $originalPrompt = '';

    public string $prompt = '';

    public array $tasks = [];

    public array $taskStatuses = [];
    
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

        $this->loadSessionData();
    }

    private function loadSessionData(): void
    {
        $session = ChatSession::query()
            ->select(['id', 'title', 'number_of_messages', 'current_model', 'current_chat_type'])
            ->find($this->sessionId);

        if ($session) {
            $this->currentModel = $session->current_model ?: $this->defaultModel;
            $this->currentChatType = $session->current_chat_type ?: $this->defaultChatType;
            $this->sessionTitle = $session->title ?? '';
            $this->numberOfMessages = $session->number_of_messages ?? 0;
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

        $chatService->findAssistantResponse(
            sessionId: $this->sessionId,
            type: $this->currentChatType,
            model: $this->currentModel,
            message: $this->originalPrompt,
            originalPrompt: $this->originalPrompt,
        );

        $this->loadSessionData();

        $this->dispatch('scroll-to-bottom');
    }

    public function workOnTask(ChatService $chatService, int $taskNum): void
    {
        $this->syncSessionConfiguration();

        $chatService->workOnTask(
            sessionId: $this->sessionId,
            type: $this->currentChatType,
            model: $this->currentModel,
            message: $this->originalPrompt,
            tasks: $this->tasks,
            task: $taskNum,
        );

        $this->loadSessionData();

        $this->taskStatuses[$taskNum] = 'Done';

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

        async sendMessage() {
            if ($wire.prompt.trim() === '') return;

            // Reset the prompt input immediately (instant client-side feedback)
            $wire.originalPrompt = $wire.prompt;
            $wire.prompt = '';

            // Add the users message and wait for it to be persisted
            await $wire.sendMessage();

            // Based on the users message, derive a tasks list
            await $wire.updateTasks();

            // Based on the task list and the original message, generate and apply a ChatSession title
            await $wire.assignSessionTitle();

            for (let i = 0; i < $wire.tasks.length; i++) {
                let taskNumber = i + 1;

                await $wire.startOnTask(taskNumber);

                await $wire.workOnTask(taskNumber);
            }

            // Only then fetch the assistant response
            {{-- await $wire.findAssistantResponse(); --}}
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
                        <flux:icon.folder-open class="size-4 text-zinc-600 dark:text-zinc-300" />
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">/user/home/...</span>
                    </div>
                </div>

                {{-- <flux:text>
                    /user/home/...
                </flux:text>

                <flux:button 
                    size="sm" 
                    variant="outline" 
                    label="Change Directory" 
                    icon="folder-open" 
                    x-on:click="$wire.sendToast('Change directory functionality not implemented yet.', '', 'danger')"
                /> --}}

                <div class="flex justify-between items-start gap-2 mt-2 mb-4">
                    <div class="w-2/3">
                        <flux:subheading size="lg">Security:</flux:subheading>
                        <ul>
                            <li>Composer (audit [Fix]) ([update])</li>
                            <li>npm (audit [Fix]) ([update])</li>
                        </ul>

                        <flux:subheading size="lg" class="mt-4">Git:</flux:subheading>
                        <div class="flex items-center gap-2 mb-2">
                            <flux:button size="sm" icon="arrow-down">Pull</flux:button>
                            <flux:button size="sm" icon="arrow-right">Commit</flux:button> {{-- suggested conventional commits in modal? on modal confirmation, git add . && git commit -m "(suggested commit message)" --}}
                            <flux:button size="sm" icon="arrow-up">Push</flux:button>
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
                    Context: x / y (z%)
                </flux:text>

                <flux:subheading>Context Actions</flux:subheading>

                <flux:button 
                    size="sm" 
                    variant="outline" 
                    label="Condense Context" 
                    icon="arrows-pointing-in" 
                    x-on:click="$wire.sendToast('Condense context functionality not implemented yet.', '', 'danger')"
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
