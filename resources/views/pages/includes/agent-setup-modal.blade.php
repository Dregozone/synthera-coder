{{-- This chat's agent: who it is (soul), the rules it keeps (guidelines), and the prompt they make --}}
<flux:modal name="agent-setup" class="md:w-[40rem]" data-test="agent-setup-modal">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Agent setup</flux:heading>
            <flux:text class="mt-2">For this chat, from its next message. Defaults for new chats, and the files themselves, are on the <flux:link href="{{ route('agent-setup', ['sessionId' => $sessionId, 'embed' => $embed ?: null]) }}">setup page</flux:link>.</flux:text>
        </div>

        <flux:radio.group wire:model.live="currentSoul" label="Soul" description="Who the agent is: its persona and tone." variant="cards" class="flex-col">
            <flux:radio value="" label="Default{{ $this->defaultSoul ? ' ('.$this->defaultSoul.')' : '' }}" description="Whatever soul new chats use. Changes when the default does." />
            @foreach ($this->soulOptions as $soul => $summary)
                <flux:radio wire:key="soul-{{ $soul }}" :value="$soul" :label="ucfirst($soul)" :description="$summary" />
            @endforeach
            <flux:radio value="{{ \App\Services\AgentSetup::NO_SOUL }}" label="No soul" description="Only the guidelines and the mode's instructions." />
        </flux:radio.group>

        <div>
            <div class="flex items-center gap-2">
                <flux:heading>Guidelines</flux:heading>
                @if ($guidelinesCustomized)
                    <flux:badge size="sm" color="amber" data-test="guidelines-custom">Custom for this chat</flux:badge>
                    <flux:button size="xs" variant="ghost" icon="arrow-uturn-left" wire:click="resetGuidelines" class="ml-auto">Use the {{ $currentChatType }} mode's</flux:button>
                @else
                    <flux:badge size="sm">From the {{ $currentChatType }} mode</flux:badge>
                @endif
            </div>
            <flux:text class="mt-1 mb-3">Standing rules the agent keeps. Tick to add, untick to drop.</flux:text>

            <flux:checkbox.group wire:model.live="selectedGuidelines">
                @foreach ($this->guidelineOptions as $guideline => $summary)
                    <flux:checkbox wire:key="guideline-{{ $guideline }}" :value="$guideline" :label="$guideline" :description="$summary" />
                @endforeach
            </flux:checkbox.group>
        </div>

        <div>
            <div class="flex items-center gap-2">
                <flux:heading>System prompt</flux:heading>
                <flux:button size="xs" variant="ghost" :icon="$showPromptPreview ? 'eye-slash' : 'eye'" wire:click="togglePromptPreview" class="ml-auto" data-test="toggle-prompt-preview">
                    {{ $showPromptPreview ? 'Hide' : 'Preview' }}
                </flux:button>
            </div>
            <flux:text class="mt-1">The soul, then the guidelines, then the {{ $currentChatType }} mode's instructions, as the model will get them.</flux:text>

            @if ($showPromptPreview)
                <pre class="mt-3 max-h-80 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-zinc-100 dark:bg-zinc-900 p-3 text-xs leading-snug" data-test="prompt-preview">{{ $this->promptPreview }}</pre>
            @endif
        </div>
    </div>
</flux:modal>
