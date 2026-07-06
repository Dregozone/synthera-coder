<?php

use App\Ai\Agents\LocalAgent;
use App\Models\AgentAction;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\AgentActionExecutor;
use App\Services\AgentRunner;
use App\Services\ContextService;
use Illuminate\Support\Facades\File;

function agentWorkspace(array $files = []): array
{
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'synthera-'.uniqid();
    File::makeDirectory($dir, 0755, true);

    foreach ($files as $relative => $contents) {
        $path = $dir.DIRECTORY_SEPARATOR.$relative;
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }

    $session = ChatSession::create([
        'current_working_directory' => $dir,
        'current_model' => 'qwen/qwen3.5-9b',
        'current_chat_type' => 'agent',
    ]);

    return [$session, $dir];
}

afterEach(function (): void {
    foreach (File::directories(sys_get_temp_dir()) as $dir) {
        if (str_contains((string) $dir, 'synthera-')) {
            File::deleteDirectory($dir);
        }
    }
});

test('agent runner persists the assistant reply and assigns a session title', function (): void {
    LocalAgent::fake(['Here is what I did and proposed.', 'Add Hello Page']);

    [$session] = agentWorkspace();

    $userMessage = ChatMessage::create([
        'chat_session_id' => $session->id,
        'type' => 'agent',
        'by' => 'user',
        'content' => 'Add a hello page',
    ]);

    app(AgentRunner::class)->runForUserMessage($session->id, $userMessage->id);

    $assistantMessage = ChatMessage::query()
        ->where('chat_session_id', $session->id)
        ->where('by', 'assistant')
        ->latest('id')
        ->first();

    expect($assistantMessage)->not->toBeNull()
        ->and($assistantMessage->content)->toBe('Here is what I did and proposed.')
        ->and($session->fresh()->title)->toBe('Add Hello Page');
});

test('agent runner refuses to run without a working directory', function (): void {
    LocalAgent::fake(['should not be used']);

    $session = ChatSession::create(['current_model' => 'qwen/qwen3.5-9b']);

    $userMessage = ChatMessage::create([
        'chat_session_id' => $session->id,
        'type' => 'agent',
        'by' => 'user',
        'content' => 'Do something',
    ]);

    app(AgentRunner::class)->runForUserMessage($session->id, $userMessage->id);

    expect(ChatMessage::query()
        ->where('chat_session_id', $session->id)
        ->where('content', 'Select a working directory before sending a request.')
        ->exists())->toBeTrue()
        ->and(ChatMessage::query()
            ->where('chat_session_id', $session->id)
            ->where('by', 'assistant')
            ->where('type', 'agent')
            ->exists())->toBeFalse();
});

test('executor applies an approved write to disk', function (): void {
    [$session, $dir] = agentWorkspace();

    $action = AgentAction::create([
        'chat_session_id' => $session->id,
        'type' => AgentAction::TYPE_WRITE,
        'status' => AgentAction::STATUS_PENDING,
        'payload' => [
            'path' => 'app/Hello.php',
            'contents' => "<?php\n// hello",
        ],
    ]);

    $result = app(AgentActionExecutor::class)->execute($action);

    expect(File::get($dir.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Hello.php'))->toBe("<?php\n// hello")
        ->and($action->fresh()->status)->toBe(AgentAction::STATUS_EXECUTED)
        ->and($result)->toContain('Wrote app/Hello.php');
});

test('executor refuses a write that escapes the working directory', function (): void {
    [$session] = agentWorkspace();

    $action = AgentAction::create([
        'chat_session_id' => $session->id,
        'type' => AgentAction::TYPE_WRITE,
        'status' => AgentAction::STATUS_PENDING,
        'payload' => [
            'path' => '../escape.php',
            'contents' => 'x',
        ],
    ]);

    $result = app(AgentActionExecutor::class)->execute($action);

    expect($action->fresh()->status)->toBe(AgentAction::STATUS_FAILED)
        ->and($result)->toContain('Refusing to write');
});

test('condensing stores a conversation summary and marks the condense point', function (): void {
    LocalAgent::fake(['A concise summary of the conversation.']);

    [$session] = agentWorkspace();

    File::deleteDirectory(app_path("Ai/Sessions/{$session->id}"));

    ChatMessage::create(['chat_session_id' => $session->id, 'type' => 'agent', 'by' => 'user', 'content' => 'Add a page']);
    $last = ChatMessage::create(['chat_session_id' => $session->id, 'type' => 'agent', 'by' => 'assistant', 'content' => 'Done, I proposed a file.']);

    $summary = app(AgentRunner::class)->condense($session->id);

    $context = new ContextService($session->id);

    expect($summary)->toBe('A concise summary of the conversation.')
        ->and($context->get('conversation_summary'))->toBe('A concise summary of the conversation.')
        ->and((int) $context->get('condensed_at_message_id'))->toBe($last->id);
});

test('executor runs an approved command and records the output', function (): void {
    [$session] = agentWorkspace();

    $action = AgentAction::create([
        'chat_session_id' => $session->id,
        'type' => AgentAction::TYPE_COMMAND,
        'status' => AgentAction::STATUS_PENDING,
        'payload' => [
            'command' => 'php -v',
            'cwd' => $session->current_working_directory,
        ],
    ]);

    $result = app(AgentActionExecutor::class)->execute($action);

    expect($action->fresh()->status)->toBe(AgentAction::STATUS_EXECUTED)
        ->and($result)->toContain('Exit code: 0')
        ->and($result)->toContain('PHP');
});
