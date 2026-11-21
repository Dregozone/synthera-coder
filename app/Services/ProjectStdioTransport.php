<?php

namespace App\Services;

use Laravel\Mcp\Client\Transport\StdioTransport;
use Laravel\Mcp\Exceptions\ClientException;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * laravel/mcp's stdio transport, starting the server process with this app's
 * environment variables unset (`false`, as Symfony Process takes them), so a
 * target project's MCP server reads its own .env rather than inheriting
 * Synthera Coder's database and keys.
 */
class ProjectStdioTransport extends StdioTransport
{
    /**
     * @param  list<string>  $args
     * @param  array<string, string|false>  $environment
     */
    public function __construct(string $command, array $args = [], private readonly array $environment = [])
    {
        parent::__construct($command, $args);
    }

    #[\Override]
    public function connect(): void
    {
        if ($this->process?->isRunning()) {
            return;
        }

        $this->input = new InputStream;
        $this->process = new Process([$this->command, ...$this->args], env: $this->environment);
        $this->process->setInput($this->input);
        $this->process->setTimeout(null);

        try {
            $this->process->start();
        } catch (ExceptionInterface) {
            throw new ClientException("Failed to start process [{$this->command}]. Make sure the command exists.");
        }
    }
}
