<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Primitives\Tool;
use Throwable;

/**
 * Laravel Boost's MCP server in the target project, as tools for the agent.
 * When the project has Boost installed, `php artisan boost:mcp` is started in
 * it over stdio (laravel/mcp's client) and the allowed, read-only Boost tools
 * (its version-aware docs search, the database schema, the last error, …) are
 * handed to the model, which laravel/ai calls like its own tools.
 */
class BoostMcp
{
    public function __construct(private readonly BackgroundLauncher $launcher) {}

    /**
     * Whether Boost is switched on here and installed in the project.
     */
    public function availableFor(?string $projectRoot): bool
    {
        return (bool) config('synthera-coder.mcp.boost.enabled', false)
            && is_string($projectRoot)
            && is_file($projectRoot.DIRECTORY_SEPARATOR.'artisan')
            && is_dir($projectRoot.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'laravel'.DIRECTORY_SEPARATOR.'boost');
    }

    /**
     * A client for the project's Boost server, limited to the allowed tools.
     * It runs with the project's own environment, not this app's.
     */
    public function client(string $projectRoot): Client
    {
        $transport = new ProjectStdioTransport(
            $this->launcher->php(),
            [$projectRoot.DIRECTORY_SEPARATOR.'artisan', 'boost:mcp'],
            CodingTools::isolatedEnvironment(),
        );

        return (new Client($transport))
            ->onlyTools(array_values((array) config('synthera-coder.mcp.boost.tools', [])))
            ->withTimeout((float) config('synthera-coder.mcp.boost.timeout', 30));
    }

    /**
     * The allowed Boost tools for the project, or none when Boost is not
     * available or its server will not start (the turn goes on without them).
     *
     * @return list<Tool>
     */
    public function tools(?string $projectRoot): array
    {
        if (! $this->availableFor($projectRoot)) {
            return [];
        }

        try {
            return $this->client((string) $projectRoot)->connect()->tools()->values()->all();
        } catch (Throwable $e) {
            Log::warning('Laravel Boost MCP tools are unavailable for '.$projectRoot.': '.$e->getMessage());

            return [];
        }
    }
}
