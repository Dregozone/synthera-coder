<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\UsesCodingTools;
use App\Services\CodingTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Reads an error the user pasted (markdown, a stack trace, a check's output):
 * its message and the project lines it points at, with the code around them.
 */
class LocateError implements Tool
{
    use UsesCodingTools;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Find where an error happened: pass the pasted error or stack trace; returns its message and each project file:line it points at, with the code around it.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->withCodingTools(fn (CodingTools $tools): string => self::asJson($tools->locateError((string) ($request['error'] ?? ''))));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'error' => $schema->string()->required(),
        ];
    }
}
