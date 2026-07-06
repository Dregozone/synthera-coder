<?php

use App\Ai\Tools\ListDirectory;
use App\Ai\Tools\ReadPackageJson;
use App\Ai\Tools\RunCommand;
use App\Ai\Tools\SearchFiles;
use App\Ai\Tools\WriteFile;
use App\Models\AgentAction;
use App\Models\ChatSession;
use App\Services\DiffService;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Tools\Request;

/**
 * Create a temporary project directory and a chat session pointed at it.
 *
 * @param  array<string, string>  $files  relative path => contents
 * @return array{0: ChatSession, 1: string}
 */
function makeWorkspace(array $files = []): array
{
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'synthera-'.uniqid();
    File::makeDirectory($dir, 0755, true);

    foreach ($files as $relative => $contents) {
        $path = $dir.DIRECTORY_SEPARATOR.$relative;
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }

    $session = ChatSession::create(['current_working_directory' => $dir]);

    return [$session, $dir];
}

afterEach(function (): void {
    foreach (File::directories(sys_get_temp_dir()) as $dir) {
        if (str_contains((string) $dir, 'synthera-')) {
            File::deleteDirectory($dir);
        }
    }
});

test('list directory returns sorted entries and rejects escaping paths', function (): void {
    [$session] = makeWorkspace([
        'composer.json' => '{}',
        'src/App.php' => '<?php',
    ]);

    $tool = new ListDirectory($session->id);

    $listing = (string) $tool->handle(new Request(['path' => '.']));

    expect($listing)->toContain('src/')
        ->and($listing)->toContain('composer.json');

    $escape = (string) $tool->handle(new Request(['path' => '../']));

    expect($escape)->toBe('Path is outside the working directory, or no working directory is set.');
});

test('search files finds matching lines and reports no matches otherwise', function (): void {
    [$session] = makeWorkspace([
        'a.txt' => "first line\nneedle here\nthird",
        'b.txt' => 'nothing relevant',
    ]);

    $tool = new SearchFiles($session->id);

    $found = (string) $tool->handle(new Request(['query' => 'needle']));

    expect($found)->toContain('a.txt:2:')
        ->and($found)->toContain('needle here');

    $missing = (string) $tool->handle(new Request(['query' => 'zzz-not-present']));

    expect($missing)->toBe('No matches found for: zzz-not-present');
});

test('read package json returns a specific version and the dependency sections', function (): void {
    [$session] = makeWorkspace([
        'package.json' => json_encode([
            'dependencies' => ['vite' => '^8.0.0'],
            'devDependencies' => ['tailwindcss' => '^4.0.7'],
            'scripts' => ['build' => 'vite build'],
        ]),
    ]);

    $tool = new ReadPackageJson($session->id);

    expect((string) $tool->handle(new Request(['package' => 'vite'])))->toBe('^8.0.0');

    $all = (string) $tool->handle(new Request([]));

    expect($all)->toContain('tailwindcss')
        ->and($all)->toContain('vite build');
});

test('write file records a pending action with a diff and does not touch disk', function (): void {
    [$session, $dir] = makeWorkspace();

    $tool = new WriteFile($session->id);

    $result = (string) $tool->handle(new Request([
        'path' => 'app/Example.php',
        'contents' => "<?php\n\necho 'hi';",
    ]));

    $action = AgentAction::query()->where('chat_session_id', $session->id)->first();

    expect($action)->not->toBeNull()
        ->and($action->type)->toBe(AgentAction::TYPE_WRITE)
        ->and($action->status)->toBe(AgentAction::STATUS_PENDING)
        ->and($action->payload['path'])->toBe('app/Example.php')
        ->and($action->payload['is_new'])->toBeTrue()
        ->and($action->payload['diff'])->toContain("+echo 'hi';")
        ->and($result)->toContain("action #{$action->id}")
        ->and(File::exists($dir.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Example.php'))->toBeFalse();
});

test('write file rejects paths outside the working directory', function (): void {
    [$session] = makeWorkspace();

    $tool = new WriteFile($session->id);

    $result = (string) $tool->handle(new Request([
        'path' => '../escape.php',
        'contents' => 'x',
    ]));

    expect($result)->toBe('Path is outside the working directory, or no working directory is set.')
        ->and(AgentAction::query()->count())->toBe(0);
});

test('run command records a pending action without executing anything', function (): void {
    [$session, $dir] = makeWorkspace();

    $tool = new RunCommand($session->id);

    $result = (string) $tool->handle(new Request(['command' => 'php artisan test']));

    $action = AgentAction::query()->where('chat_session_id', $session->id)->first();

    expect($action)->not->toBeNull()
        ->and($action->type)->toBe(AgentAction::TYPE_COMMAND)
        ->and($action->status)->toBe(AgentAction::STATUS_PENDING)
        ->and($action->payload['command'])->toBe('php artisan test')
        ->and($action->payload['cwd'])->toBe($dir)
        ->and($result)->toContain("action #{$action->id}");
});

test('diff service renders additions for new files and line changes for edits', function (): void {
    $service = new DiffService;

    expect($service->unified('', "a\nb", 'new.txt'))
        ->toContain('+a')
        ->toContain('+b');

    $diff = $service->unified("a\nb\nc", "a\nB\nc", 'edit.txt');

    expect($diff)->toContain('-b')
        ->and($diff)->toContain('+B')
        ->and($diff)->toContain(' a')
        ->and($diff)->toContain(' c');
});
