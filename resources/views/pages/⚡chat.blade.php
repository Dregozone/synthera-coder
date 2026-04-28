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
    #[Url]
    public int $sessionId = 0;

    public array $availableModels = [];

    public string $defaultModel = '';

    public string $currentModel = '';

    public array $availableChatTypes = [];

    public string $defaultChatType = '';
    
    public string $currentChatType = '';

    public string $sessionTitle = '';

    public int $numberOfMessages = 0;

    public string $prompt = '';
    
    #[Computed]
    public function messages(): Collection
    {
        return ChatMessage::where('chat_session_id', $this->sessionId)
            ->orderBy('created_at')
            ->get();
    }

    public function mount(): void
    {
        // Ensure the user has a valid session ID to track their chat session. This allows them to leave and come back without losing their place.
        if (! $this->sessionId) {
            $this->sessionId = ChatService::nextSessionId();
            $this->redirect(request()->fullUrlWithQuery(['sessionId' => $this->sessionId]));

            return;
        }

        // First time the page loads actions
        $chatService = app(ChatService::class);

        // 1 in 10 full page loads (Not livewire re-renders) will perform these maintenance tasks
        Lottery::odds(1, 10)
            ->winner(function () use ($chatService) {
                $chatService->maintenanceTasks();
            })
            ->choose();

        $this->loadSessionData();

        $this->availableModels = config('synthera-coder.chat_models', []);
        $this->defaultModel = config('synthera-coder.default_chat_model', '');

        $this->currentModel = $this->defaultModel;

        $this->availableChatTypes = config('synthera-coder.chat_types', []);
        $this->defaultChatType = config('synthera-coder.default_chat_type', '');
        $this->currentChatType = $this->defaultChatType;
    }

    private function loadSessionData(): void
    {
        $session = ChatSession::find($this->sessionId);

        if ($session) {
            $this->currentModel = $session->current_model ?? $this->defaultModel;
            $this->currentChatType = $session->current_chat_type ?? $this->defaultChatType;
            $this->sessionTitle = $session->title ?? '';
            $this->numberOfMessages = $session->number_of_messages ?? 0;
        }
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

        $this->checkSessionNumberOfMessages();
    }

    private function checkSessionNumberOfMessages(): void
    {
        $session = ChatSession::find($this->sessionId);

        $sessionMessages = ChatMessage::where('chat_session_id', $this->sessionId)->count();
        
        if ($session) {
            $session->update(['number_of_messages' => $sessionMessages]);
        }

        $this->numberOfMessages = $sessionMessages ?? 0;
    }

    public function sendMessage(ChatService $chatService): void
    {
        $chatService->sendMessage(
            sessionId: $this->sessionId,
            type: $this->currentChatType,
            model: $this->currentModel,
            message: $this->prompt,
        );

        $this->checkSessionNumberOfMessages();

        $this->dispatch('scroll-to-bottom');
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

        sendMessage() {
            if ($wire.prompt.trim() === '') return;

            @this.sendMessage()
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
                @foreach ($this->messages as $index => $message)
                    <div class="flex w-full mb-3 {{ $message['type'] === 'info' ? 'justify-center' : ($message['by'] === 'user' ? 'justify-start' : 'justify-end') }}">

                        @if ($message['type'] === 'info')
                            <flux:callout 
                                icon="exclamation-circle"
                                color="sky" 
                                inline
                                class="w-full max-w-2xl"
                            >
                                <flux:callout.heading>
                                    <div>{{ $message['content'] }}</div>
                                    <flux:spacer />
                                    <div>{{ $message['created_at']->diffForHumans() }}</div>
                                </flux:callout.heading>
                            </flux:callout>

                        @elseif ($message['by'] === 'user')
                            <flux:card>
                                user msg
                                @dump($message->getAttributes())
                            </flux:card>

                        @else
                            <flux:card>
                                assistant msg
                                @dump($message->getAttributes())
                            </flux:card>
                        @endif

                    </div>
                @endforeach
            </div>
        </div>

        <div class="ml-4">
            <form x-on:submit.prevent="sendMessage()" class="w-full">
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
    <section class="w-1/4 h-screen border-l-2 border-zinc-300 dark:border-zinc-700">
        {{-- Heading/title --}}
        <div class="w-full my-4 px-4">
            <div class="flex items-center gap-2 mb-2">
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
                    x-on:click="$wire.sendToast('Create new chat.')" 
                />
            </div>

            <flux:subheading>
                <flux:text>{{ $numberOfMessages }} {{ Str::plural('message', $numberOfMessages) }}</flux:text>
            </flux:subheading>
        </div>

        <flux:separator variant="subtle" class="mb-4" />
    </section>
</div>
