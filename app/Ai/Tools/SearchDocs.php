<?php

namespace App\Ai\Tools;

use App\Services\WebDocs;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Searches the snapshots of the approved docs sites (Laravel, Livewire, Flux…)
 * and the stubs, never the live web. Each hit names the doc to ReadDoc next
 * and the URL to cite.
 */
class SearchDocs implements Tool
{
    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Search the Laravel, Livewire and Flux docs (and starter stubs) for this project\'s versions; returns doc ids to ReadDoc and URLs to cite.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = trim((string) ($request['query'] ?? $request['value'] ?? ''));

        if ($query === '') {
            return 'Provide a few words to search for, e.g. "single file component".';
        }

        $source = trim((string) ($request['source'] ?? ''));
        $hits = app(WebDocs::class)->search($query, $source !== '' ? $source : null);

        if ($hits === []) {
            return "No docs matched: {$query}. Try other words, or say the docs do not cover it.";
        }

        return collect($hits)
            ->map(fn (array $hit): string => "[{$hit['id']}] {$hit['title']}".($hit['heading'] !== '' ? " › {$hit['heading']}" : '')
                ."\nSource: {$hit['url']}\n{$hit['excerpt']}")
            ->implode("\n\n---\n\n")
            ."\n\nRead one with ReadDoc (doc = the id in brackets, section = a heading), and cite its Source.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
            'source' => $schema->string()->description('Optional: limit to one source, e.g. laravel, livewire, flux or stubs.'),
        ];
    }
}
