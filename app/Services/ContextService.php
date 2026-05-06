<?php

namespace App\Services;

class ContextService
{
    public const MAX_CONTEXT_SIZE_BYTES = 10 * 1024;

    private ?array $context;

    public function __construct(private readonly int $chatSessionId)
    {
        $this->context = $this->findContext($this->chatSessionId);
    }

    public function findContext(int $chatSessionId): array
    {
        if ($chatSessionId === null) {
            dd('Chat session not found!');
        }

        // Check if a sessions folder exists for this chat session, if not create one
        if (! is_dir(app_path('Ai/Sessions/'.$this->chatSessionId))) {
            mkdir(app_path('Ai/Sessions/'.$this->chatSessionId), 0755, true);
        }

        // Check that a context file exists for this session, if not create one with an empty context
        if (! file_exists(app_path('Ai/Sessions/'.$this->chatSessionId.'/context.json'))) {
            file_put_contents(app_path('Ai/Sessions/'.$this->chatSessionId.'/context.json'), json_encode([]));
        }

        // Retrieve the context for this session from the context file
        $this->context = json_decode(
            file_get_contents(
                app_path("Ai/Sessions/{$this->chatSessionId}/context.json")
            ), true
        );

        if ($this->context === null) {
            // Delete the existing context file and try again
            unlink(app_path("Ai/Sessions/{$this->chatSessionId}/context.json"));

            $this->findContext($chatSessionId);
        }

        return $this->context;
    }

    public function upsertInContext(string $key, mixed $value): void
    {
        if ($this->context === null) {
            dd('Context not found!');
        }

        $this->context[$key] = $value;

        $this->persistContext();
    }

    public function addToContext(string $key, mixed $value): void
    {
        if ($this->context === null) {
            dd('Context not found!');
        }

        if (! isset($this->context[$key]) || ! is_array($this->context[$key])) {
            $this->context[$key] = [];
        }

        $this->context[$key][] = $value;

        $this->persistContext();
    }

    public function getFromContext(string $key): mixed
    {
        if ($this->context === null) {
            dd('Context not found!');
        }

        return $this->context[$key] ?? null;
    }

    public function condenseContext(): void
    {
        if ($this->context === null) {
            dd('Context not found!');
        }

        // For now we just return the context as is, but in the future we could add some logic here to condense the context down to the most important details to keep it within token limits for the LLM.
        $condensedContext = $this->context;
        // //
        $this->context = $condensedContext;

        $this->persistContext();
    }

    public function clearContext(): void
    {
        $this->context = [];

        $this->persistContext();
    }

    public function getContextFilePath(): string
    {
        if ($this->chatSessionId === null) {
            dd('Chat session ID not found!');
        }

        return app_path("Ai/Sessions/{$this->chatSessionId}/context.json");
    }

    private function persistContext(): void
    {
        if ($this->context === null) {
            dd('Context not found!');
        }

        if ($this->chatSessionId === null) {
            dd('Chat session ID not found!');
        }

        file_put_contents(app_path("Ai/Sessions/{$this->chatSessionId}/context.json"), json_encode($this->context));
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function getChatSessionId(): int
    {
        return $this->chatSessionId;
    }

    public function getContextSizeBytes(): int
    {
        $filepath = $this->getContextFilePath();

        if (file_exists($filepath)) {
            return filesize($filepath);
        }

        return 0;
    }
}
