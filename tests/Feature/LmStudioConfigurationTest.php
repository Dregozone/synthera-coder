<?php

use App\Ai\Agents\Qwen3_8b_8k;
use App\Models\ChatSession;
use App\Services\ChatService;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;

test('qwen agent is pinned to the lm studio provider and local qwen model', function (): void {
    $reflection = new ReflectionClass(Qwen3_8b_8k::class);

    $provider = $reflection->getAttributes(Provider::class)[0]->newInstance();
    $model = $reflection->getAttributes(Model::class)[0]->newInstance();

    expect($provider->value)->toBe('lmstudio');
    expect($model->value)->toBe('qwen/qwen3.5-9b');
    expect(config('ai.providers.lmstudio.driver'))->toBe('openai');
    expect(config('ai.providers.lmstudio.models.text.default'))->toBe('qwen/qwen3.5-9b');
    expect(config('synthera-coder.chat_models'))->toBe(['qwen/qwen3.5-9b']);
    expect(config('synthera-coder.default_chat_model'))->toBe('qwen/qwen3.5-9b');
});

test('chat service resolves current and legacy qwen model names to the lm studio agent', function (): void {
    $session = ChatSession::create();
    $chatService = app(ChatService::class);

    expect($chatService->findAgent('qwen/qwen3.5-9b', $session->id))->toBeInstanceOf(Qwen3_8b_8k::class);
    expect($chatService->findAgent('qwen3.5-9b', $session->id))->toBeInstanceOf(Qwen3_8b_8k::class);
    expect($chatService->findAgent('qwen3:8b-8k', $session->id))->toBeInstanceOf(Qwen3_8b_8k::class);
});
