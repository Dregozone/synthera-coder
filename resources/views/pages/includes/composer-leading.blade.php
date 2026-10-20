<flux:modal.trigger name="search" shortcut="ctrl.`">
    <flux:button size="sm" variant="subtle" icon="adjustments-horizontal" class="ml-2" />
</flux:modal.trigger>

{{-- The agent's soul, guidelines and system prompt for this chat --}}
<flux:modal.trigger name="agent-setup">
    <flux:tooltip content="Agent setup: soul, guidelines, prompt">
        <flux:button size="sm" variant="subtle" icon="user-circle" data-test="open-agent-setup" />
    </flux:tooltip>
</flux:modal.trigger>
