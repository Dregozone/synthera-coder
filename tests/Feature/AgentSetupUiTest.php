<?php

use App\Ai\Agents\LocalAgent;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\AgentRunner;
use App\Services\AgentSetup;
use App\Services\AppSettings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Ai\Prompts\AgentPrompt;
use Livewire\Livewire;

use function Pest\Laravel\get;

/*
 * Each test works on its own copy of the shipped setup files, so writing and
 * deleting files from the setup page never touches app/Ai/Setup.
 */
beforeEach(function (): void {
    $dir = storage_path('framework/testing/setup-ui-'.Str::uuid());

    File::copyDirectory(app_path('Ai/Setup'), $dir);

    config(['synthera-coder.setup_path' => $dir, 'synthera-coder.default_soul' => 'synthera']);
});

afterEach(function (): void {
    File::deleteDirectory(config('synthera-coder.setup_path'));
});

function setupFile(string $category, string $name): string
{
    return config('synthera-coder.setup_path').'/'.$category.'/'.$name.'.md';
}

test('a chat can pick its own soul, or none, and its own guidelines', function (): void {
    $setup = app(AgentSetup::class);

    expect($setup->selection('agent', ['soul' => 'mentor', 'guidelines' => ['laravel']]))->toBe([
        'soul' => 'mentor',
        'guidelines' => ['laravel'],
        'instructions' => ['agent', 'workspace'],
    ])
        ->and($setup->selection('agent', ['soul' => AgentSetup::NO_SOUL])['soul'])->toBeNull()
        ->and($setup->selection('ask', ['guidelines' => []])['guidelines'])->toBe([])
        ->and($setup->forProfile('ask', [], ['soul' => 'terse']))->toStartWith($setup->load(AgentSetup::SOUL, 'terse'));
});

test('a chat whose soul or guidelines were deleted falls back instead of failing', function (): void {
    $setup = app(AgentSetup::class);

    expect($setup->selection('agent', ['soul' => 'gone', 'guidelines' => ['laravel', 'gone', '../x']]))
        ->toMatchArray(['soul' => 'synthera', 'guidelines' => ['laravel']]);
});

test('the default soul chosen in the UI wins over config while its file exists', function (): void {
    $setup = app(AgentSetup::class);

    app(AppSettings::class)->set(AppSettings::DEFAULT_SOUL, 'mentor');
    expect($setup->defaultSoul())->toBe('mentor')
        ->and($setup->selection('agent')['soul'])->toBe('mentor')
        ->and($setup->selection('title')['soul'])->toBeNull();

    app(AppSettings::class)->set(AppSettings::DEFAULT_SOUL, 'gone');
    expect($setup->defaultSoul())->toBe('synthera');
});

test('setup files can be written and described, and only unused ones deleted', function (): void {
    $setup = app(AgentSetup::class);

    $setup->save(AgentSetup::SOUL, 'pirate', "# Who you are\n\n- Talk like a pirate.");

    expect($setup->options(AgentSetup::SOUL))->toContain('pirate')
        ->and($setup->summary(AgentSetup::SOUL, 'pirate'))->toBe('Talk like a pirate.')
        ->and($setup->usedBy(AgentSetup::SOUL, 'synthera'))->toBe(['the default soul'])
        ->and($setup->usedBy(AgentSetup::GUIDELINES, 'tool-use'))->toBe(['the plan profile', 'the agent profile'])
        ->and($setup->usedBy(AgentSetup::SOUL, 'pirate'))->toBe([]);

    $setup->delete(AgentSetup::SOUL, 'pirate');
    expect($setup->exists(AgentSetup::SOUL, 'pirate'))->toBeFalse();

    expect(fn () => $setup->delete(AgentSetup::GUIDELINES, 'laravel'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $setup->save('Elsewhere', 'x', 'x'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $setup->save(AgentSetup::SOUL, '../x', 'x'))->toThrow(InvalidArgumentException::class);
});

test('the agent runner sends the soul and guidelines the chat picked', function (): void {
    LocalAgent::fake(['Done.', 'A Title']);

    $session = ChatSession::create([
        'current_working_directory' => base_path(),
        'current_model' => 'qwen/qwen3.5-9b',
        'current_chat_type' => 'agent',
        'soul' => 'terse',
        'guidelines' => ['laravel'],
    ]);

    $message = ChatMessage::create(['chat_session_id' => $session->id, 'type' => 'agent', 'by' => 'user', 'content' => 'Hi']);

    app(AgentRunner::class)->runForUserMessage($session->id, $message->id);

    LocalAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        $instructions = (string) $prompt->agent->instructions();

        return str_contains($instructions, 'terse mode')
            && str_contains($instructions, '# Laravel guidelines')
            && ! str_contains($instructions, '# Using tools')
            && str_contains($instructions, '# Mode: Agent');
    });
});

test('the chat page picks a soul for the chat, and shows it on the agent card', function (): void {
    $session = ChatSession::create();

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->assertSee('data-test="agent-card"', escape: false)
        ->set('currentSoul', 'mentor')
        ->assertSeeHtml('Mentor')
        ->set('currentSoul', 'no-such-soul')
        ->assertSet('currentSoul', '');

    expect($session->fresh()->soul)->toBeNull();

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->set('currentSoul', AgentSetup::NO_SOUL)
        ->assertSee('No soul');

    expect($session->fresh()->soul)->toBe(AgentSetup::NO_SOUL);
});

test('a chat keeps the guidelines it ticks, and can go back to its mode\'s', function (): void {
    $session = ChatSession::create(['current_chat_type' => 'ask']);

    $page = Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->assertSet('selectedGuidelines', ['laravel', 'small-models'])
        ->assertSet('guidelinesCustomized', false)
        ->set('currentChatType', 'agent')
        ->assertSet('selectedGuidelines', ['laravel', 'tool-use', 'conventions', 'small-models'])
        ->set('selectedGuidelines', ['conventions', 'not-a-file'])
        ->assertSet('selectedGuidelines', ['conventions'])
        ->assertSet('guidelinesCustomized', true)
        ->assertSee('Custom for this chat');

    expect($session->fresh())
        ->guidelines->toBe(['conventions'])
        ->current_chat_type->toBe('agent');

    // A mode change keeps the chat's own guidelines.
    $page->set('currentChatType', 'plan')->assertSet('selectedGuidelines', ['conventions']);

    $page->call('resetGuidelines')
        ->assertSet('guidelinesCustomized', false)
        ->assertSet('selectedGuidelines', ['laravel', 'tool-use', 'small-models']);

    expect($session->fresh()->guidelines)->toBeNull();
});

test('the chat page previews the system prompt the next turn will send', function (): void {
    $session = ChatSession::create(['current_working_directory' => base_path(), 'current_chat_type' => 'plan']);

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->assertDontSee('data-test="prompt-preview"', escape: false)
        ->set('currentSoul', 'mentor')
        ->call('togglePromptPreview')
        ->assertSee('data-test="prompt-preview"', escape: false)
        ->assertSee('mentor mode')
        ->assertSee('# Mode: Plan')
        ->assertSee('Working directory: '.base_path());
});

test('new chats start with the default mode and model chosen in the UI', function (): void {
    config(['synthera-coder.chat_models' => ['qwen/qwen3.5-9b', 'qwen3.5-4b']]);

    app(AppSettings::class)->set(AppSettings::DEFAULT_CHAT_TYPE, 'plan');
    app(AppSettings::class)->set(AppSettings::DEFAULT_CHAT_MODEL, 'qwen3.5-4b');

    $session = ChatSession::create();

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->assertSet('currentChatType', 'plan')
        ->assertSet('currentModel', 'qwen3.5-4b');
});

test('the setup page shows the files, the defaults and what each profile loads', function (): void {
    get(route('agent-setup', ['sessionId' => 7]))
        ->assertOk()
        ->assertSee('data-test="file-synthera"', escape: false)
        ->assertSee('data-test="file-mentor"', escape: false)
        ->assertSee('data-test="default-soul"', escape: false)
        ->assertSee('laravel, tool-use, conventions, small-models')
        ->assertSee(route('home', ['sessionId' => 7]), escape: false);
});

test('the setup page saves the defaults for new chats', function (): void {
    config(['synthera-coder.chat_models' => ['qwen/qwen3.5-9b', 'qwen3.5-4b']]);

    Livewire::test('pages::agent-setup')
        ->set('defaultSoul', 'terse')
        ->set('defaultChatType', 'ask')
        ->set('defaultChatModel', 'qwen3.5-4b')
        ->set('defaultChatType', 'nonsense')
        ->assertSet('defaultChatType', 'ask');

    $settings = app(AppSettings::class);

    expect(app(AgentSetup::class)->defaultSoul())->toBe('terse')
        ->and($settings->defaultChatType())->toBe('ask')
        ->and($settings->defaultChatModel())->toBe('qwen3.5-4b');

    Livewire::test('pages::agent-setup')
        ->assertSet('defaultSoul', 'terse')
        ->set('defaultSoul', '');

    expect(app(AgentSetup::class)->defaultSoul())->toBe('synthera');
});

test('the setup page writes a new file, edits it and deletes it', function (): void {
    Livewire::test('pages::agent-setup')
        ->set('category', AgentSetup::GUIDELINES)
        ->call('create')
        ->set('name', 'bad name!')
        ->set('content', '# Testing')
        ->call('save')
        ->assertHasErrors('name')
        ->set('name', 'laravel')
        ->call('save')
        ->assertHasErrors('name')
        ->set('name', 'testing')
        ->set('content', "# Testing\n\n- Write a test for every change.")
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', 'testing')
        ->assertSee('Write a test for every change.')
        ->set('content', "# Testing\n\n- Run the tests.")
        ->call('save')
        ->call('delete')
        ->assertSet('editing', '');

    expect(File::exists(setupFile(AgentSetup::GUIDELINES, 'testing')))->toBeFalse();
});

test('the setup page will not delete a file a profile or the default soul needs', function (): void {
    Livewire::test('pages::agent-setup')
        ->call('open', 'synthera')
        ->assertSee('Used by the default soul')
        ->call('delete');

    expect(File::exists(setupFile(AgentSetup::SOUL, 'synthera')))->toBeTrue();
});
