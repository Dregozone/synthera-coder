<?php

use App\Services\AgentSetup;
use App\Services\AppSettings;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Agent Setup')] class extends Component
{
    // The chat to go back to, and the tighter embedded layout, carried from the chat page.
    #[Url]
    public int $sessionId = 0;

    #[Url]
    public bool $embed = false;

    #[Url]
    public string $category = AgentSetup::SOUL;

    // The defaults new chats start with ('' for the soul: config's default_soul).
    public string $defaultSoul = '';

    public string $defaultChatType = '';

    public string $defaultChatModel = '';

    // The file open in the editor ('' when none), or a new one being written.
    public string $editing = '';

    public bool $creating = false;

    public string $name = '';

    public string $content = '';

    // The profile open in the profile editor ('' when none), and its choices:
    // the soul is '' for the default soul, AgentSetup::NO_SOUL for none, or a soul's name.
    public string $editingProfile = '';

    public string $profileSoul = '';

    public array $profileGuidelines = [];

    public array $profileInstructions = [];

    public bool $showProfilePreview = false;

    public function mount(AppSettings $settings): void
    {
        if (! in_array($this->category, AgentSetup::CATEGORIES, true)) {
            $this->category = AgentSetup::SOUL;
        }

        $chosenSoul = $settings->get(AppSettings::DEFAULT_SOUL);
        $this->defaultSoul = is_string($chosenSoul) && $this->setup()->exists(AgentSetup::SOUL, $chosenSoul) ? $chosenSoul : '';
        $this->defaultChatType = $settings->defaultChatType();
        $this->defaultChatModel = $settings->defaultChatModel();
    }

    /**
     * The files in the open category, by name, with a description and what needs each.
     *
     * @return array<string, array{summary: string, used_by: array<int, string>}>
     */
    #[Computed]
    public function files(): array
    {
        $setup = $this->setup();

        return collect($setup->options($this->category))
            ->mapWithKeys(fn (string $name): array => [$name => [
                'summary' => $setup->summary($this->category, $name),
                'used_by' => $setup->usedBy($this->category, $name),
            ]])
            ->all();
    }

    /**
     * Every profile and the files it loads (without a chat's overrides), with
     * whether its soul is the default one and whether it was edited here.
     *
     * @return array<string, array{soul: string|null, default_soul: bool, guidelines: array<int, string>, instructions: array<int, string>, edited: bool}>
     */
    #[Computed]
    public function profiles(): array
    {
        $setup = $this->setup();

        return collect($setup->profiles())
            ->map(fn (array $selection, string $profile): array => [
                ...$setup->selection($profile),
                'default_soul' => ! array_key_exists('soul', $selection),
                'edited' => $setup->isProfileEdited($profile),
            ])
            ->all();
    }

    /**
     * The files a category offers, for the profile editor.
     *
     * @return array<int, string>
     */
    public function optionsFor(string $category): array
    {
        return $this->setup()->options($category);
    }

    #[Computed]
    public function currentDefaultSoul(): ?string
    {
        return $this->setup()->defaultSoul();
    }

    /**
     * The open profile's prompt as it stands in the editor, placeholders unfilled.
     */
    #[Computed]
    public function profilePreview(): string
    {
        return $this->setup()->compose([
            'soul' => match ($this->profileSoul) {
                '' => $this->currentDefaultSoul,
                AgentSetup::NO_SOUL => null,
                default => $this->profileSoul,
            },
            'guidelines' => $this->profileGuidelines,
            'instructions' => $this->profileInstructions,
        ]);
    }

    public function editProfile(string $profile): void
    {
        $profiles = $this->setup()->profiles();

        if (! array_key_exists($profile, $profiles)) {
            return;
        }

        $selection = $profiles[$profile];

        $this->resetValidation();
        $this->editingProfile = $profile;
        $this->profileSoul = match (true) {
            ! array_key_exists('soul', $selection) => '',
            $selection['soul'] === null => AgentSetup::NO_SOUL,
            default => (string) $selection['soul'],
        };
        $this->profileGuidelines = array_values($selection['guidelines'] ?? []);
        $this->profileInstructions = array_values($selection['instructions'] ?? []);
        $this->showProfilePreview = false;
    }

    /**
     * Move a guideline or an instruction earlier (-1) or later (1): they are
     * joined in this order.
     */
    public function moveInProfile(string $list, int $index, int $direction): void
    {
        if (! in_array($list, ['profileGuidelines', 'profileInstructions'], true)) {
            return;
        }

        $items = array_values($this->{$list});
        $target = $index + $direction;

        if (! isset($items[$index], $items[$target])) {
            return;
        }

        [$items[$index], $items[$target]] = [$items[$target], $items[$index]];
        $this->{$list} = $items;
    }

    public function saveProfile(): void
    {
        $this->validate([
            'profileInstructions' => ['required', 'array', 'min:1'],
        ], [
            'profileInstructions.required' => 'Pick at least one instruction: it is the task the agent is given.',
            'profileInstructions.min' => 'Pick at least one instruction: it is the task the agent is given.',
        ]);

        try {
            $this->setup()->saveProfile(
                $this->editingProfile,
                $this->profileSoul === '' ? null : $this->profileSoul,
                array_values($this->profileGuidelines),
                array_values($this->profileInstructions),
            );
        } catch (InvalidArgumentException $e) {
            Flux::toast(text: $e->getMessage(), heading: 'Not saved', variant: 'danger');

            return;
        }

        unset($this->profiles, $this->files);
        $this->saved('The '.$this->editingProfile.' profile is saved.');
        $this->closeProfile();
    }

    /**
     * Go back to what config says for the open profile.
     */
    public function resetProfile(): void
    {
        $this->setup()->resetProfile($this->editingProfile);

        unset($this->profiles, $this->files);
        $this->saved('The '.$this->editingProfile.' profile is back to its config.');
        $this->closeProfile();
    }

    public function closeProfile(): void
    {
        $this->resetValidation();
        $this->editingProfile = '';
        $this->profileSoul = '';
        $this->profileGuidelines = [];
        $this->profileInstructions = [];
        $this->showProfilePreview = false;
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function souls(): array
    {
        return $this->setup()->options(AgentSetup::SOUL);
    }

    #[Computed]
    public function configuredSoul(): ?string
    {
        return config('synthera-coder.default_soul');
    }

    public function updatedDefaultSoul(AppSettings $settings): void
    {
        if ($this->defaultSoul === '' || ! $this->setup()->exists(AgentSetup::SOUL, $this->defaultSoul)) {
            $this->defaultSoul = '';
            $settings->forget(AppSettings::DEFAULT_SOUL);
        } else {
            $settings->set(AppSettings::DEFAULT_SOUL, $this->defaultSoul);
        }

        unset($this->files, $this->profiles);
        $this->saved('Default soul saved.');
    }

    public function updatedDefaultChatType(AppSettings $settings): void
    {
        if (array_key_exists($this->defaultChatType, config('synthera-coder.chat_types', []))) {
            $settings->set(AppSettings::DEFAULT_CHAT_TYPE, $this->defaultChatType);
            $this->saved('New chats start in '.$this->defaultChatType.' mode.');
        }

        $this->defaultChatType = $settings->defaultChatType();
    }

    public function updatedDefaultChatModel(AppSettings $settings): void
    {
        if (in_array($this->defaultChatModel, config('synthera-coder.chat_models', []), true)) {
            $settings->set(AppSettings::DEFAULT_CHAT_MODEL, $this->defaultChatModel);
            $this->saved('New chats start with '.$this->defaultChatModel.'.');
        }

        $this->defaultChatModel = $settings->defaultChatModel();
    }

    public function updatedCategory(): void
    {
        if (! in_array($this->category, AgentSetup::CATEGORIES, true)) {
            $this->category = AgentSetup::SOUL;
        }

        $this->closeEditor();
    }

    public function open(string $name): void
    {
        if (! $this->setup()->exists($this->category, $name)) {
            return;
        }

        $this->resetValidation();
        $this->creating = false;
        $this->editing = $name;
        $this->name = $name;
        $this->content = $this->setup()->load($this->category, $name);
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->creating = true;
        $this->editing = '';
        $this->name = '';
        $this->content = match ($this->category) {
            AgentSetup::SOUL => "# Who you are\n\nYou are Synthera Coder, ...\n\n- \n",
            AgentSetup::GUIDELINES => "# \n\n- \n",
            default => "# \n\n",
        };
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'content' => ['required', 'string', 'max:50000'],
        ], [
            'name.regex' => 'Use only letters, numbers, dashes and underscores.',
        ]);

        if ($this->creating && $this->setup()->exists($this->category, $this->name)) {
            $this->addError('name', 'There is already a '.$this->name.'.md here.');

            return;
        }

        $this->setup()->save($this->category, $this->name, $this->content);

        $this->creating = false;
        $this->editing = $this->name;
        unset($this->files, $this->souls, $this->profiles);

        $this->saved($this->category.'/'.$this->name.'.md saved.');
    }

    public function delete(): void
    {
        if (! $this->setup()->exists($this->category, $this->editing)) {
            return;
        }

        $usedBy = $this->setup()->usedBy($this->category, $this->editing);

        if ($usedBy !== []) {
            Flux::toast(text: 'It is in use by '.implode(' and ', $usedBy).'.', heading: 'Not deleted', variant: 'warning');

            return;
        }

        $this->setup()->delete($this->category, $this->editing);
        $this->saved($this->category.'/'.$this->editing.'.md deleted.');
        unset($this->souls, $this->profiles);
        $this->closeEditor();
    }

    public function closeEditor(): void
    {
        $this->resetValidation();
        $this->creating = false;
        $this->editing = '';
        $this->name = '';
        $this->content = '';
        unset($this->files);
    }

    private function saved(string $message): void
    {
        Flux::toast(text: $message, variant: 'success');
    }

    private function setup(): AgentSetup
    {
        return app(AgentSetup::class);
    }
};
?>

<div @class(['mx-auto w-full max-w-6xl space-y-6 p-4', 'md:p-8' => ! $embed])>
    <div class="flex items-center gap-2">
        <flux:button size="sm" variant="ghost" icon="arrow-left" href="{{ route('home', ['sessionId' => $sessionId ?: null, 'embed' => $embed ?: null]) }}" data-test="back-to-chat">Back to chat</flux:button>
        <flux:heading level="1" size="xl" class="ml-2">Agent setup</flux:heading>
    </div>

    <flux:text>
        Every system prompt is a <strong>soul</strong> (who the agent is), then <strong>guidelines</strong> (rules it keeps), then <strong>instructions</strong> (the task: one per chat mode). Each chat can pick its own soul and guidelines from the chat's Agent card; here you set the defaults and write the files.
    </flux:text>

    {{-- Defaults for new chats --}}
    <flux:card class="space-y-4">
        <div>
            <flux:heading>Defaults</flux:heading>
            <flux:text>What a chat uses until it picks otherwise. Saved as soon as you choose.</flux:text>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <flux:select wire:model.live="defaultSoul" label="Soul" data-test="default-soul">
                <flux:select.option value="">From config{{ $this->configuredSoul ? ' ('.$this->configuredSoul.')' : '' }}</flux:select.option>
                @foreach ($this->souls as $soul)
                    <flux:select.option wire:key="default-soul-{{ $soul }}" :value="$soul">{{ $soul }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="defaultChatType" label="Mode" data-test="default-mode">
                @foreach (config('synthera-coder.chat_types', []) as $chatType => $iconName)
                    <flux:select.option wire:key="default-mode-{{ $chatType }}" :value="$chatType">{{ ucfirst($chatType) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="defaultChatModel" label="Model" data-test="default-model">
                @foreach (config('synthera-coder.chat_models', []) as $modelName)
                    <flux:select.option wire:key="default-model-{{ $modelName }}" :value="$modelName">{{ $modelName }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    {{-- The markdown files --}}
    <flux:card class="space-y-4">
        <div class="flex flex-wrap items-center gap-4">
            <div>
                <flux:heading>Files</flux:heading>
                <flux:text>Markdown in <code>app/Ai/Setup/{{ $category }}</code>. <code>@{{ placeholders }}</code> such as <code>@{{ working_directory }}</code> are filled in when the prompt is built.</flux:text>
            </div>

            <flux:radio.group wire:model.live="category" variant="segmented" size="sm" class="ml-auto">
                @foreach (\App\Services\AgentSetup::CATEGORIES as $option)
                    <flux:radio wire:key="category-{{ $option }}" :value="$option" :label="$option" />
                @endforeach
            </flux:radio.group>
        </div>

        <div class="grid gap-4 md:grid-cols-[18rem_1fr]">
            {{-- The files in this category --}}
            <div class="space-y-2">
                <flux:button size="sm" variant="primary" icon="plus" wire:click="create" class="w-full" data-test="new-file">New {{ strtolower(rtrim($category, 's')) }}</flux:button>

                @forelse ($this->files as $file => $meta)
                    <button
                        type="button"
                        wire:key="file-{{ $category }}-{{ $file }}"
                        wire:click="open('{{ $file }}')"
                        @class([
                            'block w-full rounded-lg border p-3 text-left transition-colors',
                            'border-violet-400 bg-violet-50 dark:border-violet-500 dark:bg-violet-950/40' => $editing === $file,
                            'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/40' => $editing !== $file,
                        ])
                        data-test="file-{{ $file }}"
                    >
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-sm text-zinc-800 dark:text-zinc-100">{{ $file }}</span>
                            @if ($meta['used_by'] !== [])
                                <flux:badge size="sm" color="zinc" class="ml-auto" title="Used by {{ implode(', ', $meta['used_by']) }}">in use</flux:badge>
                            @endif
                        </div>
                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2">{{ $meta['summary'] }}</div>
                    </button>
                @empty
                    <flux:text size="sm">No files yet.</flux:text>
                @endforelse
            </div>

            {{-- The editor --}}
            <div>
                @if ($creating || $editing !== '')
                    <form wire:submit="save" class="space-y-4" data-test="file-editor">
                        @if ($creating)
                            <flux:input wire:model="name" label="Name" description="The file name, without .md: letters, numbers, dashes and underscores." placeholder="e.g. reviewer" data-test="file-name" />
                        @else
                            <div class="flex items-center gap-2">
                                <flux:heading size="lg"><code>{{ $category }}/{{ $editing }}.md</code></flux:heading>
                                @if ($this->files[$editing]['used_by'] ?? [])
                                    <flux:text size="sm" class="ml-auto">Used by {{ implode(', ', $this->files[$editing]['used_by']) }}</flux:text>
                                @endif
                            </div>
                        @endif

                        <flux:textarea wire:model="content" label="Content" label:sr-only rows="20" class="font-mono text-sm" data-test="file-content" />

                        <div class="flex items-center gap-2">
                            <flux:button type="submit" variant="primary" icon="check">Save</flux:button>
                            <flux:button type="button" variant="ghost" wire:click="closeEditor">Close</flux:button>

                            @if (! $creating)
                                <flux:spacer />
                                @if ($this->files[$editing]['used_by'] ?? [])
                                    <flux:tooltip content="In use: change the profile in config, or the default soul, first.">
                                        <div><flux:button type="button" variant="ghost" icon="trash" disabled>Delete</flux:button></div>
                                    </flux:tooltip>
                                @else
                                    <flux:button type="button" variant="danger" icon="trash" wire:click="delete" wire:confirm="Delete {{ $category }}/{{ $editing }}.md? Chats that picked it go back to their defaults." data-test="delete-file">Delete</flux:button>
                                @endif
                            @endif
                        </div>
                    </form>
                @else
                    <div class="flex h-full min-h-48 items-center justify-center rounded-lg border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-700">
                        <flux:text>Pick a file to read or edit it, or write a new one.</flux:text>
                    </div>
                @endif
            </div>
        </div>
    </flux:card>

    {{-- What each profile loads, editable --}}
    <flux:card class="space-y-4">
        <div>
            <flux:heading>Profiles</flux:heading>
            <flux:text>Which files each kind of call loads. Each chat mode uses the profile of its name; <code>condense</code> summarises a long chat and <code>title</code> names a new one. A chat's own soul and guidelines replace its mode's. Edits here are saved over <code>agent_profiles</code> in <code>config/synthera-coder.php</code> until you reset them.</flux:text>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Profile</flux:table.column>
                <flux:table.column>Soul</flux:table.column>
                <flux:table.column>Guidelines</flux:table.column>
                <flux:table.column>Instructions</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->profiles as $profile => $selection)
                    <flux:table.row wire:key="profile-{{ $profile }}" data-test="profile-{{ $profile }}">
                        <flux:table.cell variant="strong">
                            {{ $profile }}
                            @if ($selection['edited'])
                                <flux:badge size="sm" color="amber" class="ml-1">edited</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $selection['soul'] ?? '—' }}{{ $selection['default_soul'] && $selection['soul'] ? ' (default)' : '' }}</flux:table.cell>
                        <flux:table.cell>{{ implode(', ', $selection['guidelines']) ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ implode(', ', $selection['instructions']) ?: '—' }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="editProfile('{{ $profile }}')" data-test="edit-profile-{{ $profile }}">Edit</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        @if ($editingProfile !== '')
            <form wire:submit="saveProfile" class="space-y-6 rounded-lg border border-violet-300 p-4 dark:border-violet-700" data-test="profile-editor">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">The {{ $editingProfile }} profile</flux:heading>
                    @if ($this->profiles[$editingProfile]['edited'] ?? false)
                        <flux:badge size="sm" color="amber">edited</flux:badge>
                    @endif
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <flux:radio.group wire:model.live="profileSoul" label="Soul" description="A chat can still pick its own.">
                        <flux:radio value="" label="The default soul{{ $this->currentDefaultSoul ? ' ('.$this->currentDefaultSoul.')' : '' }}" />
                        @foreach ($this->optionsFor(\App\Services\AgentSetup::SOUL) as $soul)
                            <flux:radio wire:key="profile-soul-{{ $soul }}" :value="$soul" :label="$soul" />
                        @endforeach
                        <flux:radio value="{{ \App\Services\AgentSetup::NO_SOUL }}" label="No soul" />
                    </flux:radio.group>

                    <flux:checkbox.group wire:model.live="profileGuidelines" label="Guidelines" description="Rules it keeps. A chat can tick its own.">
                        @foreach ($this->optionsFor(\App\Services\AgentSetup::GUIDELINES) as $guideline)
                            <flux:checkbox wire:key="profile-guideline-{{ $guideline }}" :value="$guideline" :label="$guideline" />
                        @endforeach
                    </flux:checkbox.group>

                    <div>
                        <flux:checkbox.group wire:model.live="profileInstructions" label="Instructions" description="The task. Keep workspace in the chat modes: it tells the agent which project it is in.">
                            @foreach ($this->optionsFor(\App\Services\AgentSetup::INSTRUCTIONS) as $instruction)
                                <flux:checkbox wire:key="profile-instruction-{{ $instruction }}" :value="$instruction" :label="$instruction" />
                            @endforeach
                        </flux:checkbox.group>
                        <flux:error name="profileInstructions" />
                    </div>
                </div>

                {{-- The order they are joined in --}}
                <div class="grid gap-6 md:grid-cols-2">
                    @foreach (['profileGuidelines' => 'Guideline order', 'profileInstructions' => 'Instruction order'] as $list => $heading)
                        <div wire:key="order-{{ $list }}">
                            <flux:heading size="sm">{{ $heading }}</flux:heading>
                            <ol class="mt-2 space-y-1">
                                @forelse (array_values($this->{$list}) as $index => $item)
                                    <li wire:key="order-{{ $list }}-{{ $item }}" class="flex items-center gap-2 rounded-md border border-zinc-200 px-2 py-1 text-sm dark:border-zinc-700">
                                        <span class="w-5 text-zinc-400">{{ $index + 1 }}.</span>
                                        <span class="grow">{{ $item }}</span>
                                        <flux:button size="xs" variant="ghost" icon="chevron-up" wire:click="moveInProfile('{{ $list }}', {{ $index }}, -1)" :disabled="$index === 0" />
                                        <flux:button size="xs" variant="ghost" icon="chevron-down" wire:click="moveInProfile('{{ $list }}', {{ $index }}, 1)" :disabled="$index === count($this->{$list}) - 1" />
                                    </li>
                                @empty
                                    <li><flux:text size="sm">None.</flux:text></li>
                                @endforelse
                            </ol>
                        </div>
                    @endforeach
                </div>

                <div>
                    <flux:button size="xs" variant="ghost" :icon="$showProfilePreview ? 'eye-slash' : 'eye'" wire:click="$toggle('showProfilePreview')" data-test="toggle-profile-preview">
                        {{ $showProfilePreview ? 'Hide the prompt' : 'Preview the prompt' }}
                    </flux:button>
                    @if ($showProfilePreview)
                        <pre class="mt-2 max-h-80 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-zinc-100 p-3 text-xs leading-snug dark:bg-zinc-900" data-test="profile-preview">{{ $this->profilePreview }}</pre>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <flux:button type="submit" variant="primary" icon="check" data-test="save-profile">Save profile</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="closeProfile">Close</flux:button>
                    @if ($this->profiles[$editingProfile]['edited'] ?? false)
                        <flux:spacer />
                        <flux:button type="button" variant="ghost" icon="arrow-uturn-left" wire:click="resetProfile" wire:confirm="Put the {{ $editingProfile }} profile back to what config says?" data-test="reset-profile">Reset to config</flux:button>
                    @endif
                </div>
            </form>
        @endif
    </flux:card>
</div>
