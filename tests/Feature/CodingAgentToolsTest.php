<?php

use App\Ai\Agents\LocalAgent;
use App\Ai\Tools\CreateApiRoute;
use App\Ai\Tools\CreateArtisanCommand;
use App\Ai\Tools\CreateFile;
use App\Ai\Tools\CreateLivewireComponent;
use App\Ai\Tools\CreateRoute;
use App\Ai\Tools\CreateTestFile;
use App\Ai\Tools\EditFile;
use App\Ai\Tools\GitDiff;
use App\Ai\Tools\LocateError;
use App\Ai\Tools\ProjectOverview;
use App\Ai\Tools\ReadFile;
use App\Ai\Tools\RunProjectCheck;
use App\Ai\Tools\SecurityReview;
use App\Models\AgentAction;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\AgentRunner;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Tools\Request;

/**
 * A temporary Laravel-ish project and a chat session working in it.
 *
 * @param  array<string, string>  $files  relative path => contents
 */
function toolsSession(array $files = [], string $mode = 'agent'): ChatSession
{
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'synthera-agent-tools-'.uniqid();
    File::makeDirectory($dir, 0755, true);
    temporaryProjects($dir);

    foreach ($files as $relative => $contents) {
        $path = $dir.DIRECTORY_SEPARATOR.$relative;
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }

    return ChatSession::create(['current_working_directory' => $dir, 'current_chat_type' => $mode, 'current_model' => 'qwen/qwen3.5-9b']);
}

afterEach(function (): void {
    foreach (temporaryProjects() as $dir) {
        File::deleteDirectory($dir);
    }
});

test('read file hands the model the contents, size and line count as JSON', function (): void {
    $session = toolsSession(['routes/web.php' => "<?php\n\n// routes\n"]);

    $result = (string) (new ReadFile($session->id))->handle(new Request(['value' => 'routes/web.php']));

    expect(json_decode($result, true))->toBe([
        'path' => 'routes/web.php',
        'contents' => "<?php\n\n// routes\n",
        'size' => 17,
        'lines' => 3,
    ]);
});

test('each creating tool proposes its change for approval and leaves the disk alone', function (string $tool, array $input, string $path, bool $isNew): void {
    $session = toolsSession([
        'routes/web.php' => "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n",
        'routes/api.php' => "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n",
        'app/Services/Greeter.php' => "<?php\n\nnamespace App\\Services;\n\nclass Greeter\n{\n    public function greet(): string { return 'hi'; }\n}\n",
    ]);
    $before = File::exists($session->current_working_directory.'/'.$path) ? File::get($session->current_working_directory.'/'.$path) : null;

    $result = (string) (new $tool($session->id))->handle(new Request($input));

    $action = AgentAction::query()->where('chat_session_id', $session->id)->sole();

    expect($action->type)->toBe(AgentAction::TYPE_WRITE)
        ->and($action->status)->toBe(AgentAction::STATUS_PENDING)
        ->and($action->payload['path'])->toBe($path)
        ->and($action->payload['is_new'])->toBe($isNew)
        ->and($action->payload['diff'])->toContain('+')
        ->and($result)->toContain("action #{$action->id}")
        ->and(File::exists($session->current_working_directory.'/'.$path) ? File::get($session->current_working_directory.'/'.$path) : null)->toBe($before);
})->with([
    'CreateFile' => [CreateFile::class, ['path' => 'docs/notes.md', 'contents' => '# Notes'], 'docs/notes.md', true],
    'EditFile' => [EditFile::class, ['path' => 'app/Services/Greeter.php', 'search' => "'hi'", 'replace' => "'hello'"], 'app/Services/Greeter.php', false],
    'CreateRoute' => [CreateRoute::class, ['path' => '/testing', 'action' => "return 'ok';"], 'routes/web.php', false],
    'CreateApiRoute' => [CreateApiRoute::class, ['method' => 'post', 'path' => '/status', 'action' => "return ['ok' => true];"], 'routes/api.php', false],
    'CreateLivewireComponent' => [CreateLivewireComponent::class, ['name' => 'pages::users.index'], 'resources/views/pages/users/⚡index.blade.php', true],
    'CreateArtisanCommand' => [CreateArtisanCommand::class, ['signature' => 'do:something {userId}', 'description' => 'Do it', 'handle' => 'return self::SUCCESS;'], 'app/Console/Commands/DoSomething.php', true],
    'CreateTestFile' => [CreateTestFile::class, ['class_path' => 'app/Services/Greeter.php'], 'tests/Feature/GreeterTest.php', true],
]);

test('create route reads middleware as a comma-separated list and groups the route', function (): void {
    $session = toolsSession(['routes/web.php' => "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n"]);

    (new CreateRoute($session->id))->handle(new Request([
        'path' => '/reports',
        'action' => "return 'reports';",
        'middleware' => 'auth, verified',
        'prefix' => 'admin',
    ]));

    expect(AgentAction::query()->sole()->payload['contents'])
        ->toContain("Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {");
});

test('a creating tool hands back what was wrong with its input and proposes nothing', function (): void {
    $session = toolsSession(['notes.txt' => 'hello']);

    $result = (string) (new EditFile($session->id))->handle(new Request(['path' => 'notes.txt', 'search' => 'absent', 'replace' => 'x']));

    expect($result)->toContain('was not found in notes.txt')
        ->and(AgentAction::query()->count())->toBe(0);
});

test('the tools need a working directory', function (string $tool): void {
    $session = ChatSession::create([]);

    expect((string) (new $tool($session->id))->handle(new Request([])))->toBe('No working directory is set for this session.');
})->with([CreateFile::class, ProjectOverview::class, RunProjectCheck::class, SecurityReview::class]);

test('run project check proposes the configured command, and only a configured one', function (): void {
    $session = toolsSession();

    $result = (string) (new RunProjectCheck($session->id))->handle(new Request(['check' => 'phpstan']));
    $refused = (string) (new RunProjectCheck($session->id))->handle(new Request(['check' => 'rm -rf .']));

    $action = AgentAction::query()->sole();

    expect($action->type)->toBe(AgentAction::TYPE_COMMAND)
        ->and($action->payload)->toBe(['command' => 'php vendor/bin/phpstan analyse --no-progress', 'cwd' => $session->current_working_directory])
        ->and($result)->toContain("action #{$action->id}")
        ->and($refused)->toContain('Check must be one of: pint, rector, phpstan, tests, routes');
});

test('the read-only tools answer straight away', function (string $tool, array $input, string $expected): void {
    $session = toolsSession([
        'composer.json' => json_encode(['name' => 'acme/shop', 'require' => ['laravel/framework' => '^13.0']]),
        'README.md' => "# Acme\n\nA shop.",
        'app/Models/Post.php' => "<?php\n\nclass Post\n{\n    protected \$guarded = [];\n}\n",
    ]);

    expect((string) (new $tool($session->id))->handle(new Request($input)))->toContain($expected)
        ->and(AgentAction::query()->count())->toBe(0);
})->with([
    'ProjectOverview' => [ProjectOverview::class, [], '"laravel/framework": "^13.0"'],
    'LocateError' => [LocateError::class, ['error' => 'ErrorException: Undefined variable $x in app/Models/Post.php on line 5'], '"line": 5'],
    'SecurityReview' => [SecurityReview::class, [], 'app/Models/Post.php:5 Every attribute mass assignable'],
    'GitDiff outside a repository' => [GitDiff::class, [], 'git diff failed'],
]);

test('plan mode gets the read-only coding tools, and agent mode the creating tools too', function (string $mode, array $has, array $hasNot): void {
    LocalAgent::fake(['Done.', 'Title']);

    $session = toolsSession(mode: $mode);
    $message = ChatMessage::create(['chat_session_id' => $session->id, 'type' => $mode, 'by' => 'user', 'content' => 'Hi']);

    app(AgentRunner::class)->runForUserMessage($session->id, $message->id);

    LocalAgent::assertPrompted(function (AgentPrompt $prompt) use ($has, $hasNot): bool {
        $tools = collect($prompt->agent->tools())->map(fn (object $tool): string => class_basename($tool));

        return $tools->intersect($has)->count() === count($has) && $tools->intersect($hasNot)->isEmpty();
    });
})->with([
    'plan' => ['plan', ['ProjectOverview', 'GitDiff', 'LocateError', 'SecurityReview'], ['CreateRoute', 'EditFile', 'RunProjectCheck']],
    'agent' => ['agent', ['ProjectOverview', 'CreateFile', 'EditFile', 'CreateRoute', 'CreateApiRoute', 'CreateLivewireComponent', 'CreateArtisanCommand', 'CreateTestFile', 'RunProjectCheck'], []],
]);
