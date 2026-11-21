<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\UsesCodingTools;
use App\Services\CodingTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * What the project is: its description and versions, its Composer and npm
 * dependencies, and its markdown docs.
 */
class ProjectOverview implements Tool
{
    use UsesCodingTools;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Describe the project: name, description, Laravel/Livewire versions, README intro, Composer and npm dependencies, and its docs (markdown files).';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->withCodingTools(fn (CodingTools $tools): string => self::asJson([
            ...$tools->overview(),
            'dependencies' => $tools->dependencies(),
            'docs' => $tools->documentation(),
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
