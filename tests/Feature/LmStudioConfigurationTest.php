<?php

use App\Ai\Agents\LocalAgent;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\ChatService;
use Laravel\Ai\Prompts\AgentPrompt;

test('lm studio is configured as an openai-compatible local provider', function (): void {
    expect(config('ai.providers.lmstudio.driver'))->toBe('openai')
        ->and(config('ai.providers.lmstudio.models.text.default'))->toBe('qwen/qwen3.5-9b')
        ->and(config('synthera-coder.chat_models'))->toBe(['qwen/qwen3.5-9b', 'qwen3.5-4b'])
        ->and(config('synthera-coder.default_chat_model'))->toBe('qwen/qwen3.5-9b');
});

test('the default model is registered in the models map with lm studio provider', function (): void {
    $models = config('synthera-coder.models');

    expect($models)->toHaveKey('qwen/qwen3.5-9b');

    $config = $models['qwen/qwen3.5-9b'];

    expect($config['provider'])->toBe('lmstudio')
        ->and($config['model'])->toBe('qwen/qwen3.5-9b')
        ->and($config['context_window'])->toBeInt()
        ->and($config['max_steps'])->toBeInt()
        ->and($config['timeout'])->toBeInt();
});

test('chat service resolves a configured model label to a local agent', function (): void {
    $session = ChatSession::create();
    $chatService = app(ChatService::class);

    $agent = $chatService->findAgent('qwen/qwen3.5-9b', $session->id);

    expect($agent)->toBeInstanceOf(LocalAgent::class)
        ->and($agent->provider)->toBe('lmstudio')
        ->and($agent->model)->toBe('qwen/qwen3.5-9b');

    $smallAgent = $chatService->findAgent('qwen3.5-4b', $session->id);

    expect($smallAgent)->toBeInstanceOf(LocalAgent::class)
        ->and($smallAgent->model)->toBe('qwen3.5-4b');
});

test('local agent ask routes prompts through the configured provider and model', function (): void {
    LocalAgent::fake(['Hello from the fake local model']);

    $agent = new LocalAgent(provider: 'lmstudio', model: 'qwen/qwen3.5-9b', timeout: 300);

    $response = $agent->ask('Say hello');

    expect($response->text)->toBe('Hello from the fake local model');

    LocalAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Say hello'));
});

test('chat service returns null and records an info message for an unknown model', function (): void {
    $session = ChatSession::create();
    $chatService = app(ChatService::class);

    $agent = $chatService->findAgent('does-not-exist', $session->id);

    expect($agent)->toBeNull()
        ->and(ChatMessage::query()
            ->where('chat_session_id', $session->id)
            ->where('content', 'The specified model is not recognized...')
            ->exists())->toBeTrue();
});
