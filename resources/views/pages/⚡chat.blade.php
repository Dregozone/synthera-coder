<?php

use App\Models\ChatSession;
use App\Services\ChatService;
use Illuminate\Support\Lottery;
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

    public function sendMessage(ChatService $chatService): void
    {
        $chatService->sendMessage(
            type: $this->currentChatType,
            model: $this->currentModel,
            message: $this->prompt,
        );
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
        numberOfMessages: {{ $numberOfMessages }},

        sendMessage() {
            if ($wire.prompt.trim() === '') return;

            this.numberOfMessages++;
    
            @this.sendMessage();
        }
    }"
    id="container" 
    class="w-full flex gap-4"
>
    {{-- Left side --}}
    <section class="w-3/4 flex flex-col h-screen py-4">
        {{-- Main output screen --}}
        <div class="grow ml-4 mb-4 p-4 border border-zinc-300 dark:border-zinc-700 rounded-2xl shadow-lg overflow-y-auto">
            @dump($sessionId)

            @dump(
                $currentModel,
                $currentChatType,
                $sessionTitle,
                $numberOfMessages,
            )

            <p class="text-center text-zinc-500">Chat messages will appear here...</p>
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
            <flux:heading level="1" size="xl">
                {{ $sessionTitle ?: 'New Chat Session' }}
            </flux:heading>

            <flux:subheading>
                <flux:text x-text="numberOfMessages + ' ' + (numberOfMessages === 1 ? 'message' : 'messages')"></flux:text>
            </flux:subheading>
        </div>

        <flux:separator variant="subtle" class="mb-4" />
    </section>
</div>
