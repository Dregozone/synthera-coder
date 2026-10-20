<?php

use App\Services\AgentSetup;
use App\Services\AppSettings;
use App\Services\DocSources;
use App\Services\WebDocs;
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

    // The docs source open in its editor ('' when none), or a new one being added.
    public string $editingSource = '';

    public bool $creatingSource = false;

    public string $sourceKey = '';

    public string $sourceName = '';

    public string $sourceBaseUrl = '';

    // One page path per line.
    public string $sourcePages = '';

    public bool $sourceEnabled = true;

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

    /**
     * Every approved docs source, with how much of it has been fetched and when.
     *
     * @return array<string, array{name: string, base_url: string, pages: int, fetched: int, enabled: bool, checked_at: string|null, stale: bool, configured: bool, edited: bool}>
     */
    #[Computed]
    public function docSources(): array
    {
        $webDocs = app(WebDocs::class);
        $sources = app(DocSources::class);

        return collect($sources->all())
            ->map(fn (array $source, string $key): array => [
                'name' => $source['name'],
                'base_url' => $source['base_url'],
                'pages' => count($source['pages']),
                'fetched' => collect($source['pages'])->filter(fn (string $page): bool => $webDocs->snapshot($key, $page) !== null)->count(),
                'enabled' => $source['enabled'],
                'checked_at' => $webDocs->checkedAt($key)?->diffForHumans(),
                'stale' => $webDocs->isStale($key),
                'configured' => $sources->isConfigured($key),
                'edited' => $sources->isEdited($key),
            ])
            ->all();
    }

    /**
     * The open source's pages and when each was last changed ('' when never fetched).
     *
     * @return array<string, string>
     */
    #[Computed]
    public function sourcePageStatus(): array
    {
        $source = app(DocSources::class)->find($this->editingSource);

        if ($source === null) {
            return [];
        }

        $webDocs = app(WebDocs::class);

        return collect($source['pages'])
            ->mapWithKeys(fn (string $page): array => [$page => (string) ($webDocs->snapshot($this->editingSource, $page)['fetched_at'] ?? '')])
            ->all();
    }

    /**
     * The stubs, by name, with the docs pages each cites.
     *
     * @return array<string, array{file: string, sources: array<int, string>, content: string}>
     */
    #[Computed]
    public function stubs(): array
    {
        return app(WebDocs::class)->stubs();
    }

    public function newSource(): void
    {
        $this->closeSource();
        $this->creatingSource = true;
    }

    public function editSource(string $key): void
    {
        $source = app(DocSources::class)->find($key);

        if ($source === null) {
            return;
        }

        $this->resetValidation();
        $this->creatingSource = false;
        $this->editingSource = $key;
        $this->sourceKey = $key;
        $this->sourceName = $source['name'];
        $this->sourceBaseUrl = $source['base_url'];
        $this->sourcePages = implode("\n", $source['pages']);
        $this->sourceEnabled = $source['enabled'];
        unset($this->sourcePageStatus);
    }

    public function saveSource(): void
    {
        $this->validate([
            'sourceKey' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9-]{0,39}$/'],
            'sourceName' => ['required', 'string', 'max:80'],
            'sourceBaseUrl' => ['required', 'string', 'max:255', 'starts_with:https://'],
            'sourcePages' => ['required', 'string', 'max:10000'],
        ], [
            'sourceKey.regex' => 'Use lowercase letters, numbers and dashes.',
            'sourceBaseUrl.starts_with' => 'Only https:// sites can be approved.',
        ]);

        $sources = app(DocSources::class);
        $key = $this->creatingSource ? $this->sourceKey : $this->editingSource;

        if ($this->creatingSource && $sources->find($key) !== null) {
            $this->addError('sourceKey', 'There is already a source called '.$key.'.');

            return;
        }

        try {
            $sources->save($key, $this->sourceName, $this->sourceBaseUrl, preg_split('/\R/', $this->sourcePages) ?: [], $this->sourceEnabled);
        } catch (InvalidArgumentException $e) {
            $this->addError(str_starts_with($e->getMessage(), 'Not a page') ? 'sourcePages' : 'sourceBaseUrl', $e->getMessage());

            return;
        }

        unset($this->docSources);
        $this->saved('The '.$key.' docs source is saved. Refresh it to fetch its pages.');
        $this->editSource($key);
    }

    /**
     * Drop the open source's edit: back to config, or removed if it was added here.
     */
    public function resetSource(): void
    {
        $sources = app(DocSources::class);
        $key = $this->editingSource;
        $configured = $sources->isConfigured($key);

        $sources->forget($key);

        unset($this->docSources);
        $this->saved($configured ? 'The '.$key.' docs source is back to its config.' : 'The '.$key.' docs source is removed.');
        $this->closeSource();
    }

    public function closeSource(): void
    {
        $this->resetValidation();
        $this->editingSource = '';
        $this->creatingSource = false;
        $this->sourceKey = '';
        $this->sourceName = '';
        $this->sourceBaseUrl = '';
        $this->sourcePages = '';
        $this->sourceEnabled = true;
    }

    /**
     * Fetch one source's pages (or every enabled source's) into the snapshots now.
     */
    public function refreshDocs(?string $key = null): void
    {
        if ($key !== null && app(DocSources::class)->find($key) === null) {
            return;
        }

        $results = collect(app(WebDocs::class)->refresh($key));

        $changed = $results->sum(fn (array $result): int => count($result['changed']));
        $unchanged = $results->sum(fn (array $result): int => count($result['unchanged']));
        $failed = $results->flatMap(fn (array $result, string $source): array => collect($result['failed'])
            ->map(fn (string $error, string $page): string => $source.'/'.$page.': '.$error)
            ->values()
            ->all());

        unset($this->docSources, $this->sourcePageStatus);

        Flux::toast(
            text: "{$changed} changed, {$unchanged} unchanged".($failed->isNotEmpty() ? ', '.$failed->count().' failed. First: '.$failed->first() : '.'),
            heading: 'Docs refreshed',
            variant: $failed->isNotEmpty() ? 'warning' : 'success',
        );
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

    {{-- Approved docs sites and their snapshots --}}
    <flux:card class="space-y-4" data-test="docs-sources">
        <div class="flex flex-wrap items-start gap-4">
            <div class="max-w-3xl">
                <flux:heading>Docs</flux:heading>
                <flux:text>The agent looks things up in snapshots of these approved docs sites (<code>app/Ai/WebDocs</code>) with SearchDocs and ReadDoc, and cites the pages it used. It never fetches the web itself: only a refresh does, weekly or from here, and only the listed pages of an https site, with no redirects away from it. Edits are saved over <code>doc_sources</code> in <code>config/synthera-coder.php</code>.</flux:text>
            </div>

            <div class="ml-auto flex gap-2">
                <flux:button size="sm" icon="arrow-path" wire:click="refreshDocs" wire:loading.attr="disabled" wire:target="refreshDocs" data-test="refresh-all-docs">Refresh all</flux:button>
                <flux:button size="sm" variant="primary" icon="plus" wire:click="newSource" data-test="new-source">Add a site</flux:button>
            </div>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Source</flux:table.column>
                <flux:table.column>Site</flux:table.column>
                <flux:table.column>Pages</flux:table.column>
                <flux:table.column>Refreshed</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->docSources as $key => $source)
                    <flux:table.row wire:key="source-{{ $key }}" data-test="source-{{ $key }}">
                        <flux:table.cell variant="strong">
                            {{ $source['name'] }} <span class="font-normal text-zinc-400">{{ $key }}</span>
                            @if (! $source['enabled'])
                                <flux:badge size="sm" color="zinc" class="ml-1">off</flux:badge>
                            @endif
                            @if ($source['edited'])
                                <flux:badge size="sm" color="amber" class="ml-1">{{ $source['configured'] ? 'edited' : 'added' }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell><code class="text-xs">{{ $source['base_url'] }}</code></flux:table.cell>
                        <flux:table.cell>{{ $source['fetched'] }} / {{ $source['pages'] }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $source['checked_at'] ?? 'Never' }}
                            @if ($source['enabled'] && $source['stale'])
                                <flux:badge size="sm" color="red" class="ml-1">due</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="xs" variant="ghost" icon="arrow-path" wire:click="refreshDocs('{{ $key }}')" wire:loading.attr="disabled" wire:target="refreshDocs" data-test="refresh-source-{{ $key }}">Refresh</flux:button>
                            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="editSource('{{ $key }}')" data-test="edit-source-{{ $key }}">Edit</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        @if ($creatingSource || $editingSource !== '')
            <form wire:submit="saveSource" class="space-y-4 rounded-lg border border-violet-300 p-4 dark:border-violet-700" data-test="source-editor">
                <flux:heading size="lg">{{ $creatingSource ? 'Add a docs site' : 'The '.$editingSource.' docs source' }}</flux:heading>

                <div class="grid gap-4 md:grid-cols-3">
                    @if ($creatingSource)
                        <flux:input wire:model="sourceKey" label="Key" description="Its folder in app/Ai/WebDocs." placeholder="e.g. pest" data-test="source-key" />
                    @else
                        <flux:input :value="$editingSource" label="Key" description="Its folder in app/Ai/WebDocs." disabled />
                    @endif
                    <flux:input wire:model="sourceName" label="Name" placeholder="e.g. Pest 4" data-test="source-name" />
                    <flux:input wire:model="sourceBaseUrl" label="Base URL" description="https only; pages are fetched under it." placeholder="https://pestphp.com/docs/" data-test="source-base-url" />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:textarea wire:model="sourcePages" label="Pages" description="One path under the base URL per line, e.g. writing-tests." rows="8" class="font-mono text-sm" data-test="source-pages" />

                    <div class="space-y-3">
                        <flux:switch wire:model="sourceEnabled" label="Enabled" description="Off: not refreshed, and the agent does not read it." />

                        @if ($editingSource !== '')
                            <div>
                                <flux:heading size="sm">Snapshots</flux:heading>
                                <ul class="mt-1 max-h-48 space-y-0.5 overflow-auto text-xs" data-test="source-page-status">
                                    @foreach ($this->sourcePageStatus as $page => $fetchedAt)
                                        <li wire:key="page-status-{{ $page }}" class="flex gap-2">
                                            <code class="grow">{{ $page }}</code>
                                            <span @class(['text-zinc-500' => $fetchedAt !== '', 'text-red-600 dark:text-red-400' => $fetchedAt === ''])>{{ $fetchedAt !== '' ? \Illuminate\Support\Carbon::parse($fetchedAt)->toDateString() : 'not fetched' }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <flux:button type="submit" variant="primary" icon="check" data-test="save-source">Save</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="closeSource">Close</flux:button>
                    @if ($editingSource !== '' && ($this->docSources[$editingSource]['edited'] ?? false))
                        <flux:spacer />
                        @if ($this->docSources[$editingSource]['configured'])
                            <flux:button type="button" variant="ghost" icon="arrow-uturn-left" wire:click="resetSource" wire:confirm="Put the {{ $editingSource }} source back to what config says?" data-test="reset-source">Reset to config</flux:button>
                        @else
                            <flux:button type="button" variant="danger" icon="trash" wire:click="resetSource" wire:confirm="Remove the {{ $editingSource }} docs source? The agent stops reading it." data-test="remove-source">Remove</flux:button>
                        @endif
                    @endif
                </div>
            </form>
        @endif

        <div>
            <flux:heading size="sm">Stubs</flux:heading>
            <flux:text size="sm">Starting files in <code>resources/views/Ai/stubs</code> the agent copies from, each citing the docs page it follows.</flux:text>
            <ul class="mt-2 space-y-1 text-sm" data-test="stubs">
                @forelse ($this->stubs as $name => $stub)
                    <li wire:key="stub-{{ $name }}" class="flex flex-wrap items-center gap-2">
                        <code>{{ $stub['file'] }}</code>
                        @forelse ($stub['sources'] as $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="text-xs text-violet-600 hover:underline dark:text-violet-400">{{ $url }}</a>
                        @empty
                            <flux:badge size="sm" color="red">no source cited</flux:badge>
                        @endforelse
                    </li>
                @empty
                    <li><flux:text size="sm">No stubs yet.</flux:text></li>
                @endforelse
            </ul>
        </div>
    </flux:card>
</div>
