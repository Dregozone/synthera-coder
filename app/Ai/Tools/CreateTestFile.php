<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\UsesCodingTools;
use App\Models\ChatSession;
use App\Services\CodingTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Proposes a Pest test file for a class; the user approves it first.
 */
class CreateTestFile implements Tool
{
    use UsesCodingTools;

    public function __construct(protected ?int $chatSessionId = null) {}

    public function description(): Stringable|string
    {
        return 'Propose a Pest test file (tests/Feature/{Class}Test.php) for the class in a file, with a todo per public method to fill in with EditFile.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->withCodingTools(fn (CodingTools $tools, ChatSession $session): string => $this->proposeChange(
            $session,
            $tools->planTestFile((string) ($request['class_path'] ?? '')),
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'class_path' => $schema->string()->required()->description('The class file, e.g. app/Services/InvoiceTotals.php'),
        ];
    }
}
