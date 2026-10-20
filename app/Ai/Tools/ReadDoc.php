<?php

namespace App\Ai\Tools;

use App\Services\WebDocs;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Reads one docs page snapshot or stub found with SearchDocs, headed by the URL
 * to cite. The URL is noted on the chat so the reply cites it even when the
 * model forgets to.
 */
class ReadDoc implements Tool
{
    private const int MAX_CHARACTERS = 12000;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Read a docs page or stub by its id from SearchDocs (e.g. livewire/components or stubs/livewire-sfc), optionally one section.';
    }

    public function handle(Request $request): Stringable|string
    {
        $id = trim((string) ($request['doc'] ?? $request['value'] ?? ''));

        if ($id === '') {
            return 'Provide a doc id from SearchDocs, e.g. livewire/components.';
        }

        $webDocs = app(WebDocs::class);
        $section = trim((string) ($request['section'] ?? ''));
        $document = $webDocs->read($id, $section !== '' ? $section : null);

        if ($document === null) {
            return "No doc with the id {$id}. Use SearchDocs to find one.";
        }

        if (trim($document['content']) === '') {
            return "{$id} has no section matching \"{$section}\". Its sections: ".implode('; ', $webDocs->headings((string) $webDocs->read($id)['content']));
        }

        if ($this->chatSessionId !== null && $document['url'] !== '') {
            $webDocs->cite($this->chatSessionId, $document['url']);
        }

        $content = $document['content'];

        if (mb_strlen($content) > self::MAX_CHARACTERS) {
            $content = Str::limit($content, self::MAX_CHARACTERS)
                ."\n\n(Cut short. Ask for one section; its sections: ".implode('; ', $webDocs->headings($document['content'])).')';
        }

        return "Source: {$document['url']}\n\n{$content}";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'doc' => $schema->string()->required(),
            'section' => $schema->string()->description('Optional: only the sections whose heading contains this.'),
        ];
    }
}
