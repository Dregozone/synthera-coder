<?php

namespace App\Jobs;

use App\Services\AgentRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunAgentTurn implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    /**
     * @param  'user'|'continue'  $kind
     */
    public function __construct(
        public int $sessionId,
        public string $kind,
        public ?int $userMessageId = null,
        public ?string $feedback = null,
    ) {}

    public function handle(AgentRunner $runner): void
    {
        if ($this->kind === 'user' && $this->userMessageId !== null) {
            $runner->runForUserMessage($this->sessionId, $this->userMessageId);

            return;
        }

        if ($this->kind === 'continue' && $this->feedback !== null) {
            $runner->continueAfterActions($this->sessionId, $this->feedback);
        }
    }
}
