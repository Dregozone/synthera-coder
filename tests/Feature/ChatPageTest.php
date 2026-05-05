<?php

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\ChatService;
use Illuminate\Support\Carbon;
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
