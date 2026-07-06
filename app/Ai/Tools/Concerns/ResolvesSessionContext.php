<?php

namespace App\Ai\Tools\Concerns;

use App\Models\ChatSession;

/**
 * Shared helpers for tools that operate within a chat session's working
 * directory. Tools may be constructed with an explicit session id (as the agent
 * orchestrator does), or fall back to the most recently active session when
 * auto-discovered with no arguments.
 *
 * Consuming classes must expose a nullable `$chatSessionId` property.
 */
trait ResolvesSessionContext
{
    protected function currentSession(): ?ChatSession
    {
        if ($this->chatSessionId !== null) {
            return ChatSession::find($this->chatSessionId);
        }

        return ChatSession::query()->latest('updated_at')->first();
    }

    protected function workingDirectory(): ?string
    {
        return $this->currentSession()?->current_working_directory;
    }
}
