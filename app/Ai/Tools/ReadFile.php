<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\UsesCodingTools;
use App\Services\CodingTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ReadFile implements Tool
{
    use UsesCodingTools;

    public function __construct(protected ?int $chatSessionId = null) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Read a file (path relative to the project root). Returns JSON: path, contents, size (bytes) and lines.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        return $this->withCodingTools(fn (CodingTools $tools): string => self::asJson($tools->readFile((string) $request['value'])));
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'value' => $schema->string()->required(),
        ];
    }
}
