{{-- Soul selection: who the agent is in this chat --}}
<flux:select
    wire:model.live="currentSoul"
    variant="listbox"
    size="sm"
    class="ml-2 max-w-[10rem]"
    data-test="soul-select"
>
    <flux:select.option value="">
        <div class="flex items-center gap-2">
            <flux:icon.face-smile variant="mini" class="text-zinc-400" /> Default{{ $this->defaultSoul ? ' ('.$this->defaultSoul.')' : '' }}
        </div>
    </flux:select.option>
    @foreach ($this->soulOptions as $soul => $summary)
        <flux:select.option :value="$soul">
            <div class="flex items-center gap-2" title="{{ $summary }}">
                <flux:icon.face-smile variant="mini" class="text-zinc-400" /> {{ ucfirst($soul) }}
            </div>
        </flux:select.option>
    @endforeach
    <flux:select.option value="{{ \App\Services\AgentSetup::NO_SOUL }}">
        <div class="flex items-center gap-2">
            <flux:icon.no-symbol variant="mini" class="text-zinc-400" /> No soul
        </div>
    </flux:select.option>
</flux:select>

{{-- Chat type selection --}}
<flux:select 
    wire:model.live="currentChatType"
    variant="listbox" 
    size="sm"
    class="ml-2 max-w-[10rem]"
>
    @foreach ($availableChatTypes as $chatType => $iconName)
        <flux:select.option :value="$chatType">
            <div class="flex items-center gap-2">
                <flux:icon name="{{ $iconName }}" variant="mini" class="text-zinc-400" /> {{ ucfirst($chatType) }}
            </div>
        </flux:select.option>
    @endforeach
</flux:select>


{{-- Model selection --}}
<flux:select 
    wire:model="currentModel"
    size="sm"
    class="ml-2 max-w-[13rem]"
>
    @foreach ($availableModels as $modelName)
        <flux:select.option :value="$modelName">
            {{ $modelName }}
        </flux:select.option>
    @endforeach
</flux:select>


{{-- Send button --}}
<flux:button type="submit" size="sm" variant="primary" icon="paper-airplane" class="ml-2 min-w-[4rem]" :loading="false" x-bind:disabled="$wire.prompt.trim() === ''" />
