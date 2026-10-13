<?php

use App\Ai\Agents\LocalAgent;
use App\Models\ChatSession;
use App\Services\LmStudio;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    config(['ai.providers.lmstudio.url' => 'http://127.0.0.1:1234/v1']);
});

test('each LM Studio request carries how long the model may sit idle (ttl), so it unloads itself', function (): void {
    config(['synthera-coder.model_ttl' => 300]);

    expect((new LocalAgent(provider: 'lmstudio'))->providerOptions('openai'))->toBe(['ttl' => 300])
        ->and((new LocalAgent(provider: 'ollama'))->providerOptions('ollama'))->toBe([]);

    config(['synthera-coder.model_ttl' => 0]);
    expect((new LocalAgent(provider: 'lmstudio'))->providerOptions('openai'))->toBe([]);
});

test('the model status says whether it is loaded (holding the GPU) or not', function (): void {
    Http::fake([
        '127.0.0.1:1234/api/v0/models' => Http::sequence()
            ->push(['data' => [['id' => 'qwen/qwen3.5-9b', 'state' => 'loaded']]])
            ->push(['data' => [['id' => 'qwen/qwen3.5-9b', 'state' => 'not-loaded']]])
            ->push(['data' => []])
            ->push('Not found', 404),
        '127.0.0.1:1234/v1/models' => Http::response(['data' => []]),
    ]);

    $lm = app(LmStudio::class);
    expect($lm->status('qwen/qwen3.5-9b'))->toBe('warm')
        ->and($lm->status('qwen/qwen3.5-9b'))->toBe('cold')
        ->and($lm->status('qwen/qwen3.5-9b'))->toBe('reachable')
        ->and($lm->status('qwen/qwen3.5-9b'))->toBe('reachable');
});

test('LM Studio not running is offline', function (): void {
    Http::fake(fn () => throw new ConnectionException('refused'));

    expect(app(LmStudio::class)->status('qwen/qwen3.5-9b'))->toBe('offline');
});

test('Unload now asks LM Studio to unload the model, and the page says so', function (): void {
    Http::fake([
        '127.0.0.1:1234/api/v1/models/unload' => Http::response(['ok' => true]),
        '127.0.0.1:1234/api/v0/models' => Http::response(['data' => [['id' => 'qwen/qwen3.5-9b', 'state' => 'not-loaded']]]),
    ]);
    $session = ChatSession::create(['current_model' => 'qwen/qwen3.5-9b']);

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->set('modelStatus', 'warm')
        ->assertSee('Unload now')
        ->call('unloadModel')
        ->assertSet('modelStatus', 'cold')
        ->assertSee('Not loaded');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/v1/models/unload') && $request['instance_id'] === 'qwen/qwen3.5-9b');
});
