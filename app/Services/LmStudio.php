<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

/**
 * LM Studio, beyond the chat requests: whether a model is loaded (holding the
 * GPU) and unloading it. Models load when first asked (LM Studio's JIT
 * loading) and unload after `model_ttl` idle seconds; this unloads one now.
 */
class LmStudio
{
    /**
     * `warm` (loaded), `cold` (on hand, loads when asked), `reachable` (server
     * up, state unknown) or `offline`.
     */
    public function status(string $modelId): string
    {
        try {
            $native = Http::timeout(3)->get($this->root().'/api/v0/models');

            if ($native->ok() && is_array($native->json('data'))) {
                $model = collect($native->json('data'))->firstWhere('id', $modelId);

                return match ($model['state'] ?? null) {
                    'loaded' => 'warm',
                    'not-loaded' => 'cold',
                    default => 'reachable',
                };
            }

            $compatible = Http::timeout(3)->get($this->base().'/models');

            return $compatible->ok() ? 'reachable' : 'offline';
        } catch (\Throwable) {
            return 'offline';
        }
    }

    /**
     * Unload a model now (frees the GPU). Through LM Studio's API, else its
     * `lms` command. Whether it worked.
     */
    public function unload(string $modelId): bool
    {
        try {
            if (Http::timeout(10)->post($this->root().'/api/v1/models/unload', ['instance_id' => $modelId])->successful()) {
                return true;
            }
        } catch (\Throwable) {
            // Try the command line.
        }

        $lms = $this->lms();

        if ($lms === null) {
            return false;
        }

        $process = new Process([$lms, 'unload', $modelId]);
        $process->setTimeout(30);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * The OpenAI-compatible address (`http://127.0.0.1:1234/v1`).
     */
    private function base(): string
    {
        return rtrim((string) config('ai.providers.lmstudio.url'), '/');
    }

    /**
     * The server itself (`http://127.0.0.1:1234`), for LM Studio's own API.
     */
    private function root(): string
    {
        return preg_replace('~/v1$~', '', $this->base()) ?? $this->base();
    }

    /**
     * LM Studio's `lms` command: on the PATH, or where LM Studio puts it.
     */
    private function lms(): ?string
    {
        $home = getenv('USERPROFILE') ?: getenv('HOME') ?: '';

        foreach ([$home.'/.lmstudio/bin/lms.exe', $home.'/.lmstudio/bin/lms'] as $candidate) {
            if ($home !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
