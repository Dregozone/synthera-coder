{{-- Chat type selection --}}
<flux:select 
    wire:model="currentChatType"
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
