<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\AgentResponse;
use Stringable;

/**
 * A single, runtime-configurable agent backed by a local provider (LM Studio /
 * Ollama). Provider, model, timeout, system instructions, conversation history,
 * and tools are all injected at construction time so that models can be swapped
 * purely through configuration rather than a class-per-model.
 */
#[MaxSteps(25)]
class LocalAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  array<int, Message>  $conversation
     * @param  array<int, Tool>  $availableTools
     */
    public function __construct(
        public string $provider = 'lmstudio',
        public string $model = 'qwen/qwen3.5-9b',
        public int $timeout = 300,
        public string $systemInstructions = 'You are a helpful assistant.',
        public array $conversation = [],
        public array $availableTools = [],
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->systemInstructions;
    }

    /**
     * @return Message[]
     */
    public function messages(): iterable
    {
        return $this->conversation;
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return $this->availableTools;
    }

    /**
     * Prompt the agent using its configured provider, model, and timeout.
     */
    public function ask(string $prompt): AgentResponse
    {
        return $this->prompt(
            $prompt,
            provider: $this->provider,
            model: $this->model,
            timeout: $this->timeout,
        );
    }
}
