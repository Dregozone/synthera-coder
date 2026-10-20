<?php

namespace App\Console\Commands;

use App\Services\DocSources;
use App\Services\WebDocs;
use Illuminate\Console\Command;

/**
 * Fetches the approved docs sources' pages into the snapshots the agent reads
 * (app/Ai/WebDocs). Scheduled weekly, and run from the setup page.
 */
class RefreshWebDocs extends Command
{
    protected $signature = 'synthera:refresh-docs {source? : Only this source\'s key, e.g. livewire}';

    protected $description = 'Fetch the approved docs sites\' pages into the snapshots the agent reads';

    public function handle(WebDocs $webDocs, DocSources $sources): int
    {
        $key = $this->argument('source');

        if ($key !== null && $sources->find($key) === null) {
            $this->error("There is no docs source called {$key}.");

            return self::FAILURE;
        }

        $failed = false;

        foreach ($webDocs->refresh($key) as $sourceKey => $result) {
            $this->line(sprintf(
                '<info>%s</info>: %d changed, %d unchanged, %d failed',
                $sourceKey,
                count($result['changed']),
                count($result['unchanged']),
                count($result['failed']),
            ));

            foreach ($result['failed'] as $page => $error) {
                $this->warn("  {$page}: {$error}");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
