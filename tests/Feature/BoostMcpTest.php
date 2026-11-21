<?php

use App\Ai\Agents\LocalAgent;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\AgentRunner;
use App\Services\BoostMcp;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Tools\Request;
use Laravel\Mcp\Client\Primitives\Tool as McpTool;

/**
 * A temporary project, with Laravel Boost installed or not.
 */
function boostProject(bool $withBoost): string
{
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'synthera-boost-'.uniqid();
    File::makeDirectory($dir, 0755, true);
    File::put($dir.'/artisan', '<?php');
    temporaryProjects($dir);

    if ($withBoost) {
        File::makeDirectory($dir.'/vendor/laravel/boost', 0755, true);
    }

    return $dir;
}

afterEach(function (): void {
    foreach (temporaryProjects() as $dir) {
        File::deleteDirectory($dir);
    }
});

test('Boost is offered for a Laravel project that has it installed, when switched on', function (bool $enabled, bool $withBoost, bool $expected): void {
    config(['synthera-coder.mcp.boost.enabled' => $enabled]);

    expect(app(BoostMcp::class)->availableFor(boostProject($withBoost)))->toBe($expected);
})->with([
    'on, installed' => [true, true, true],
    'on, not installed' => [true, false, false],
    'off, installed' => [false, true, false],
]);

test('no Boost tools, and no process started, for a project without Boost', function (): void {
    config(['synthera-coder.mcp.boost.enabled' => true]);

    expect(app(BoostMcp::class)->tools(boostProject(false)))->toBe([])
        ->and(app(BoostMcp::class)->tools(null))->toBe([]);
});

test('a Boost server that will not start gives no tools rather than failing the turn', function (): void {
    config(['synthera-coder.mcp.boost.enabled' => true, 'synthera-coder.php_binary' => 'no-such-php-binary']);

    expect(app(BoostMcp::class)->tools(boostProject(true)))->toBe([]);
});

test('it lists the allowed tools from a real Boost MCP server over stdio', function (): void {
    config(['synthera-coder.mcp.boost.enabled' => true, 'synthera-coder.mcp.boost.tools' => ['database-schema', 'search-docs']]);

    $names = collect(app(BoostMcp::class)->tools(base_path()))
        ->map(fn (McpTool $tool): string => $tool->name)
        ->sort()
        ->values()
        ->all();

    expect($names)->toBe(['database-schema', 'search-docs']);
});

test('plan and agent mode get the Boost tools, ask mode does not', function (string $mode, bool $expected): void {
    LocalAgent::fake(['Done.', 'Title']);

    $boostTool = new class implements Tool
    {
        public function description(): string
        {
            return 'Boost database schema';
        }

        public function handle(Request $request): string
        {
            return '';
        }

        public function schema(JsonSchema $schema): array
        {
            return [];
        }
    };

    $this->mock(BoostMcp::class)->shouldReceive('tools')->andReturn([$boostTool]);

    $session = ChatSession::create(['current_working_directory' => boostProject(true), 'current_chat_type' => $mode]);
    $message = ChatMessage::create(['chat_session_id' => $session->id, 'type' => $mode, 'by' => 'user', 'content' => 'Hi']);

    app(AgentRunner::class)->runForUserMessage($session->id, $message->id);

    LocalAgent::assertPrompted(fn (AgentPrompt $prompt): bool => in_array($boostTool, iterator_to_array($prompt->agent->tools()), true) === $expected);
})->with([
    ['plan', true],
    ['agent', true],
    ['ask', false],
]);
