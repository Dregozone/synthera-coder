<?php

namespace App\Services;

class ContextService
{
    public const MAX_CONTEXT_SIZE_BYTES = 32 * 1024;

    private array $context;

    public function __construct(private readonly int $chatSessionId)
    {
        $this->context = $this->findContext($this->chatSessionId);
    }

    public function findContext(int $chatSessionId): array
    {
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
        $this->set($key, $value);
    }

    public function addToContext(string $key, mixed $value): void
    {
        $this->push($key, $value);
    }

    public function getFromContext(string $key): mixed
    {
        return $this->get($key);
    }

    public function set(string $key, mixed $value): void
    {
        data_set($this->context, $key, $value);

        $this->persistContext();
    }

    public function push(string $key, mixed $value): void
    {
        $items = data_get($this->context, $key, []);

        if (! is_array($items)) {
            $items = [];
        }

        $items[] = $value;

        data_set($this->context, $key, $items);

        $this->persistContext();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->context, $key, $default);
    }

    public function condenseContext(): void
    {
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
        return app_path("Ai/Sessions/{$this->chatSessionId}/context.json");
    }

    private function persistContext(): void
    {
        file_put_contents(app_path("Ai/Sessions/{$this->chatSessionId}/context.json"), json_encode($this->context));
    }

    public function getContext(): array
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
