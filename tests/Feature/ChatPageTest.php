<?php

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\ChatService;
use App\Services\ContextService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Mockery\MockInterface;

use function Pest\Laravel\get;

test('chat page initially renders only the most recent messages', function (): void {
    $session = ChatSession::create([
        'number_of_messages' => 60,
    ]);

    foreach (range(1, 60) as $index) {
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'type' => 'chat',
            'by' => 'assistant',
            'content' => sprintf('Message token %03d', $index),
        ]);
    }

    $response = get(route('home', ['sessionId' => $session->id]));

    $response->assertOk();
    $response->assertSeeText('Message token 060');
    $response->assertDontSeeText('Message token 001');
    $response->assertSeeText('Load 10 older messages');
});

test('older messages can be loaded on demand', function (): void {
    $session = ChatSession::create([
        'number_of_messages' => 60,
    ]);

    foreach (range(1, 60) as $index) {
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'type' => 'chat',
            'by' => 'assistant',
            'content' => sprintf('Message token %03d', $index),
        ]);
    }

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->call('loadOlderMessages')
        ->assertSeeText('Message token 001');
});

test('working directory switch button closes the modal via flux alpine helper', function (): void {
    $session = ChatSession::create();

    $response = get(route('home', ['sessionId' => $session->id]));

    $response->assertOk();
    $response->assertSee('$flux.modal(\'change-directory\').close()', escape: false);
});

test('chat page includes bucketed thinking messages and resets the timer state', function (): void {
    $session = ChatSession::create();

    $response = get(route('home', ['sessionId' => $session->id]));

    $response->assertOk();
    $response->assertSee('thinkingStageBuckets:', escape: false);
    $response->assertSee('Warming up the gears...');
    $response->assertSee('Preparing the brainwaves...');
    $response->assertSee('Thinking harder...');
    $response->assertSee('Going full detective mode...');
    $response->assertSee('Reasoning at full tilt...');
    $response->assertSee('this.selectedThinkingStages = this.pickThinkingStages()', escape: false);
    $response->assertSee('this.selectedThinkingStages = []', escape: false);
    $response->assertSee('this.thinkingStartedAt = Date.now()', escape: false);
    $response->assertSee('window.clearInterval(this.thinkingMessageTimer)', escape: false);
});

test('adding a message increments the cached session counters', function (): void {
    $session = ChatSession::create();
    $originalUpdatedAt = $session->updated_at->copy();

    Carbon::setTestNow($originalUpdatedAt->copy()->addSecond());

    app(ChatService::class)->addMessage(
        sessionId: $session->id,
        type: 'chat',
        by: 'assistant',
        content: 'Hello from the agent',
    );

    Carbon::setTestNow();

    $session->refresh();

    expect($session->number_of_messages)->toBe(1);
    expect($session->updated_at->greaterThan($originalUpdatedAt))->toBeTrue();
});

test('chat page hydrates workflow state from the current request context file', function (): void {
    $session = ChatSession::create([
        'current_model' => 'qwen3:8b-8k',
        'current_chat_type' => 'chat',
    ]);

    File::deleteDirectory(app_path("Ai/Sessions/{$session->id}"));

    $contextService = new ContextService($session->id);
    $contextService->set('requests', [[
        'original_message' => 'Inspect the composer constraints',
        'agent' => 'qwen3:8b-8k',
        'message_type' => 'chat',
        'working_directory' => base_path(),
        'tasks' => [
            [
                'number' => 1,
                'content' => 'Read composer.json',
                'status' => 'Done',
                'tool_calls' => [],
                'tool_results' => [[
                    'tool' => 'ReadFile',
                    'input' => 'composer.json',
                    'output' => '{}',
                ]],
                'summary' => 'Read composer file',
                'response' => 'Composer file reviewed',
            ],
            [
                'number' => 2,
                'content' => 'Summarize the findings',
                'status' => 'Pending',
                'tool_calls' => [],
                'tool_results' => [],
                'summary' => null,
                'response' => null,
            ],
        ],
        'final_response' => null,
        'created_at' => now()->toIso8601String(),
        'updated_at' => now()->toIso8601String(),
    ]]);
    $contextService->set('current_request_index', 0);

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        // Tasks are displayed from the 0-based request task list, while status/result maps stay keyed by 1-based task number.
        ->assertSet('originalPrompt', 'Inspect the composer constraints')
        ->assertSet('tasks.0', 'Read composer.json')
        ->assertSet('taskStatuses.1', 'Done')
        ->assertSet('toolResults.1.0.tool', 'ReadFile')
        ->assertSet('taskResults.1', 'Composer file reviewed');
});

test('assistant response reads task results from context and stores the final response', function (): void {
    $session = ChatSession::create([
        'current_model' => 'qwen3:8b-8k',
        'current_chat_type' => 'chat',
    ]);

    File::deleteDirectory(app_path("Ai/Sessions/{$session->id}"));

    $contextService = new ContextService($session->id);
    $contextService->set('requests', [[
        'original_message' => 'Original prompt from context',
        'agent' => 'qwen3:8b-8k',
        'message_type' => 'chat',
        'working_directory' => base_path(),
        'tasks' => [
            [
                'number' => 1,
                'content' => 'Inspect the file',
                'status' => 'Done',
                'tool_calls' => [],
                'tool_results' => [[
                    'tool' => 'ReadFile',
                    'input' => 'composer.json',
                    'output' => '{}',
                ]],
                'summary' => 'Inspected file',
                'response' => 'Task response from context',
            ],
        ],
        'final_response' => null,
        'created_at' => now()->toIso8601String(),
        'updated_at' => now()->toIso8601String(),
    ]]);
    $contextService->set('current_request_index', 0);

    $this->mock(ChatService::class, function (MockInterface $mock) use ($session): void {
        $mock->shouldReceive('maintenanceTasks')->zeroOrMoreTimes();
        $mock->shouldReceive('findAssistantResponse')
            ->once()
            ->withArgs(function (int $sessionId, string $type, string $model, string $message, string $originalPrompt, array $taskResults) use ($session): bool {
                expect($sessionId)->toBe($session->id);
                expect($type)->toBe('chat');
                expect($model)->toBe('qwen3:8b-8k');
                expect($message)->toBe('Original prompt from context');
                expect($originalPrompt)->toBe('Original prompt from context');
                expect($taskResults)->toBe([1 => 'Task response from context']);

                return true;
            })
            ->andReturn('Final answer from context');
    });

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->call('findAssistantResponse');

    expect((new ContextService($session->id))->get('requests.0.final_response.content'))
        ->toBe('Final answer from context');
});

test('adding a message stores chat history in the context file', function (): void {
    $session = ChatSession::create();

    File::deleteDirectory(app_path("Ai/Sessions/{$session->id}"));

    app(ChatService::class)->addMessage(
        sessionId: $session->id,
        type: 'chat',
        by: 'assistant',
        content: 'Hello from the context history',
    );

    $contextService = new ContextService($session->id);

    expect($contextService->get('chat_history.0.by'))->toBe('assistant')
        ->and($contextService->get('chat_history.0.type'))->toBe('chat')
        ->and($contextService->get('chat_history.0.content'))->toBe('Hello from the context history');
});
