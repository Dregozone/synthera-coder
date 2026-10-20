<?php

use App\Ai\Agents\LocalAgent;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\AgentRunner;
use App\Services\AgentSetup;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Prompts\AgentPrompt;

function setupFixture(): string
{
    $dir = storage_path('framework/testing/setup-'.uniqid());

    File::ensureDirectoryExists($dir.'/Soul');
    File::ensureDirectoryExists($dir.'/Guidelines');
    File::ensureDirectoryExists($dir.'/Instructions');

    File::put($dir.'/Soul/friendly.md', "# Friendly\n\nBe friendly.\n");
    File::put($dir.'/Soul/terse.md', 'Be terse.');
    File::put($dir.'/Guidelines/tests.md', 'Always write tests.');
    File::put($dir.'/Instructions/greet.md', 'Greet {{ name }} in {{ place }}.');
    File::put($dir.'/Instructions/notes.txt', 'Not markdown.');

    return $dir;
}

afterEach(function (): void {
    foreach (File::directories(storage_path('framework/testing')) as $dir) {
        if (str_contains((string) $dir, 'setup-')) {
            File::deleteDirectory($dir);
        }
    }
});

test('it lists the markdown options in a category', function (): void {
    $setup = new AgentSetup(setupFixture());

    expect($setup->options(AgentSetup::SOUL))->toBe(['friendly', 'terse'])
        ->and($setup->options(AgentSetup::INSTRUCTIONS))->toBe(['greet'])
        ->and($setup->options('Missing'))->toBe([]);
});

test('it composes soul, guidelines and instructions in order and fills placeholders', function (): void {
    $setup = new AgentSetup(setupFixture());

    $prompt = $setup->compose([
        'soul' => 'friendly',
        'guidelines' => ['tests'],
        'instructions' => ['greet'],
    ], ['name' => 'Ada', 'place' => 'London']);

    expect($prompt)->toBe("# Friendly\n\nBe friendly.".PHP_EOL.PHP_EOL.'Always write tests.'.PHP_EOL.PHP_EOL.'Greet Ada in London.');
});

test('unknown placeholders are left as they are', function (): void {
    $setup = new AgentSetup(setupFixture());

    expect($setup->compose(['instructions' => ['greet']], ['name' => 'Ada']))
        ->toBe('Greet Ada in {{ place }}.');
});

test('it refuses missing files and names that could leave the setup directory', function (string $name): void {
    (new AgentSetup(setupFixture()))->load(AgentSetup::SOUL, $name);
})->throws(InvalidArgumentException::class)->with(['missing', '../Guidelines/tests', 'friendly.md']);

test('a profile uses the default soul unless it sets its own or none', function (): void {
    config([
        'synthera-coder.default_soul' => 'friendly',
        'synthera-coder.agent_profiles' => [
            'default' => ['instructions' => ['greet']],
            'own' => ['soul' => 'terse', 'instructions' => ['greet']],
            'none' => ['soul' => null, 'instructions' => ['greet']],
        ],
    ]);

    $setup = new AgentSetup(setupFixture());

    expect($setup->forProfile('default'))->toStartWith('# Friendly')
        ->and($setup->forProfile('own'))->toStartWith('Be terse.')
        ->and($setup->forProfile('none'))->toStartWith('Greet');
});

test('an unconfigured profile is refused', function (): void {
    (new AgentSetup(setupFixture()))->forProfile('nope');
})->throws(InvalidArgumentException::class);

test('every configured profile loads from the shipped setup files', function (): void {
    $setup = app(AgentSetup::class);
    $profiles = array_keys(config('synthera-coder.agent_profiles'));

    expect($profiles)->toContain('ask', 'plan', 'agent', 'condense', 'title');

    foreach ($profiles as $profile) {
        expect($setup->forProfile($profile))->not->toBe('');
    }
});

test('every shipped soul can be chosen as the default', function (): void {
    $setup = app(AgentSetup::class);

    foreach ($setup->options(AgentSetup::SOUL) as $soul) {
        config(['synthera-coder.default_soul' => $soul]);

        expect($setup->forProfile('agent'))->toStartWith($setup->load(AgentSetup::SOUL, $soul));
    }

    expect($setup->options(AgentSetup::SOUL))->toContain('synthera', 'mentor', 'terse');
});

test('the agent runner sends the mode profile as the system prompt', function (string $mode, string $expected, string $absent): void {
    LocalAgent::fake(['Done.', 'A Title']);

    $dir = storage_path('framework/testing/setup-workspace-'.uniqid());
    File::ensureDirectoryExists($dir.'/app');

    $session = ChatSession::create([
        'current_working_directory' => $dir,
        'current_model' => 'qwen/qwen3.5-9b',
        'current_chat_type' => $mode,
    ]);

    $message = ChatMessage::create([
        'chat_session_id' => $session->id,
        'type' => $mode,
        'by' => 'user',
        'content' => 'Hello there',
    ]);

    app(AgentRunner::class)->runForUserMessage($session->id, $message->id);

    LocalAgent::assertPrompted(function (AgentPrompt $prompt) use ($expected, $absent, $dir): bool {
        $instructions = (string) $prompt->agent->instructions();

        return str_contains($instructions, 'You are Synthera Coder')
            && str_contains($instructions, $expected)
            && ! str_contains($instructions, $absent)
            && str_contains($instructions, 'Working directory: '.$dir)
            && str_contains($instructions, 'Top-level contents: app/');
    });

    LocalAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains((string) $prompt->agent->instructions(), 'Task: title a chat')
        && $prompt->contains('Hello there'));
})->with([
    'ask' => ['ask', '# Mode: Ask', '# Using tools'],
    'plan' => ['plan', '# Mode: Plan', '# Mode: Agent'],
    'agent' => ['agent', '# Mode: Agent', '# Mode: Plan'],
]);
