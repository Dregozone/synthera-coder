<?php

use App\Jobs\RunAgentTurn;
use App\Models\AgentAction;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\ChatService;
use App\Services\ContextService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

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

test('the chat shows a working indicator and polls while awaiting a response', function (): void {
    Queue::fake();

    $session = ChatSession::create([
        'current_working_directory' => base_path(),
        'current_model' => 'qwen/qwen3.5-9b',
        'current_chat_type' => 'agent',
    ]);

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->set('prompt', 'Do something')
        ->call('sendMessage')
        ->assertSet('awaitingResponse', true)
        ->assertSee('Working locally')
        ->assertSee('wire:poll.2s', escape: false);
});

test('deleting a session removes it with its messages and redirects home', function (): void {
    $session = ChatSession::create(['current_working_directory' => base_path()]);

    ChatMessage::create([
        'chat_session_id' => $session->id,
        'type' => 'agent',
        'by' => 'user',
        'content' => 'A message',
    ]);

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->call('deleteSession')
        ->assertRedirect(route('home'));

    expect(ChatSession::find($session->id))->toBeNull()
        ->and(ChatMessage::where('chat_session_id', $session->id)->exists())->toBeFalse();
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

test('sending a message stores the user message and dispatches an agent turn', function (): void {
    Queue::fake();

    $session = ChatSession::create([
        'current_model' => 'qwen/qwen3.5-9b',
        'current_chat_type' => 'agent',
        'current_working_directory' => base_path(),
    ]);

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->set('prompt', 'Add a hello page')
        ->call('sendMessage')
        ->assertSet('prompt', '')
        ->assertSet('awaitingResponse', true);

    expect(ChatMessage::query()
        ->where('chat_session_id', $session->id)
        ->where('by', 'user')
        ->where('content', 'Add a hello page')
        ->exists())->toBeTrue();

    Queue::assertPushed(
        RunAgentTurn::class,
        fn (RunAgentTurn $job): bool => $job->sessionId === $session->id && $job->kind === 'user',
    );
});

test('sending a message without a working directory is rejected before dispatch', function (): void {
    Queue::fake();

    $session = ChatSession::create(['current_model' => 'qwen/qwen3.5-9b']);

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->set('prompt', 'Add a hello page')
        ->call('sendMessage')
        ->assertSet('awaitingResponse', false);

    Queue::assertNothingPushed();

    expect(ChatMessage::query()->where('chat_session_id', $session->id)->exists())->toBeFalse();
});

test('approving a proposed write applies it and queues a continuation turn', function (): void {
    Queue::fake();

    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'synthera-'.uniqid();
    File::makeDirectory($dir, 0755, true);

    $session = ChatSession::create([
        'current_working_directory' => $dir,
        'current_model' => 'qwen/qwen3.5-9b',
        'current_chat_type' => 'agent',
    ]);

    File::deleteDirectory(app_path("Ai/Sessions/{$session->id}"));

    $action = AgentAction::create([
        'chat_session_id' => $session->id,
        'type' => AgentAction::TYPE_WRITE,
        'status' => AgentAction::STATUS_PENDING,
        'payload' => ['path' => 'notes.txt', 'contents' => 'hello world'],
    ]);

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->call('approveAction', $action->id);

    expect(File::get($dir.DIRECTORY_SEPARATOR.'notes.txt'))->toBe('hello world')
        ->and($action->fresh()->status)->toBe(AgentAction::STATUS_EXECUTED);

    Queue::assertPushed(
        RunAgentTurn::class,
        fn (RunAgentTurn $job): bool => $job->kind === 'continue',
    );

    File::deleteDirectory($dir);
});

test('the automatic follow-up loop stops after the continuation limit is reached', function (): void {
    Queue::fake();

    $session = ChatSession::create([
        'current_working_directory' => base_path(),
        'current_model' => 'qwen/qwen3.5-9b',
        'current_chat_type' => 'agent',
    ]);

    File::deleteDirectory(app_path("Ai/Sessions/{$session->id}"));

    // Simulate having already used the full budget of automatic follow-ups.
    (new ContextService($session->id))->set('continuation_rounds', 6);

    $action = AgentAction::create([
        'chat_session_id' => $session->id,
        'type' => AgentAction::TYPE_COMMAND,
        'status' => AgentAction::STATUS_PENDING,
        'payload' => ['command' => 'php artisan test', 'cwd' => base_path()],
    ]);

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->call('rejectAction', $action->id);

    Queue::assertNotPushed(RunAgentTurn::class);

    expect(ChatMessage::query()
        ->where('chat_session_id', $session->id)
        ->where('content', 'like', 'Reached the automatic follow-up limit%')
        ->exists())->toBeTrue();
});

test('rejecting a proposed action marks it rejected and queues a continuation turn', function (): void {
    Queue::fake();

    $session = ChatSession::create([
        'current_working_directory' => base_path(),
        'current_model' => 'qwen/qwen3.5-9b',
        'current_chat_type' => 'agent',
    ]);

    File::deleteDirectory(app_path("Ai/Sessions/{$session->id}"));

    $action = AgentAction::create([
        'chat_session_id' => $session->id,
        'type' => AgentAction::TYPE_COMMAND,
        'status' => AgentAction::STATUS_PENDING,
        'payload' => ['command' => 'php artisan migrate', 'cwd' => base_path()],
    ]);

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->call('rejectAction', $action->id);

    expect($action->fresh()->status)->toBe(AgentAction::STATUS_REJECTED);

    Queue::assertPushed(
        RunAgentTurn::class,
        fn (RunAgentTurn $job): bool => $job->kind === 'continue',
    );
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
