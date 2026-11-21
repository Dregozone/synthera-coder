<?php

/*
 * The coding checklist from issue #29, one or more tests per scenario, run
 * against the CodingTools service the agent's tools sit on. The /testing
 * closure route and grouping routes under shared middleware or prefix are in
 * CodingToolsTest with the other route tests.
 */

use App\Console\Commands\DoSomething;
use App\Models\User;
use App\Services\CodingTools;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * @param  array<string, string>  $files  relative path => contents
 */
function scenarioProject(array $files = []): string
{
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'synthera-scenario-'.uniqid();
    File::makeDirectory($dir, 0755, true);
    temporaryProjects($dir);

    foreach ($files as $relative => $contents) {
        $path = $dir.DIRECTORY_SEPARATOR.$relative;
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }

    return $dir;
}

/**
 * Run git in a folder, failing the test if it fails.
 *
 * @param  list<string>  $arguments
 */
function git(string $dir, array $arguments): void
{
    $result = Process::path($dir)->run(['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', '-c', 'commit.gpgsign=false', ...$arguments]);

    expect($result->successful())->toBeTrue($result->errorOutput());
}

afterEach(function (): void {
    foreach (temporaryProjects() as $dir) {
        File::deleteDirectory($dir);
    }
});

test('project description: names the project, its description, framework versions and README intro', function (): void {
    $tools = new CodingTools(scenarioProject([
        'composer.json' => json_encode(['name' => 'acme/shop', 'description' => 'An online shop.']),
        'composer.lock' => json_encode(['packages' => [
            ['name' => 'laravel/framework', 'version' => 'v13.2.0'],
            ['name' => 'livewire/livewire', 'version' => 'v4.1.0'],
        ]]),
        'README.md' => "# Acme Shop\n\n[![badge](x)](y)\n\nSells widgets to\nwidget lovers.\n\n## Install\n\nRun it.",
    ]));

    expect($tools->overview())->toMatchArray([
        'name' => 'acme/shop',
        'description' => 'An online shop.',
        'laravel' => 'v13.2.0',
        'livewire' => 'v4.1.0',
        'readme' => 'Sells widgets to widget lovers.',
    ]);
});

test('project description: copes with a folder that is not a PHP project', function (): void {
    $dir = scenarioProject();

    expect((new CodingTools($dir))->overview())->toMatchArray([
        'name' => basename($dir),
        'description' => null,
        'laravel' => null,
        'readme' => null,
    ]);
});

test('dependency list: lists Composer and npm dependencies', function (): void {
    $tools = new CodingTools(scenarioProject([
        'composer.json' => json_encode(['require' => ['php' => '^8.3', 'laravel/framework' => '^13.0'], 'require-dev' => ['pestphp/pest' => '^5.0']]),
        'package.json' => json_encode(['devDependencies' => ['vite' => '^8.0']]),
    ]));

    expect($tools->dependencies())->toBe([
        'composer' => ['require' => ['php' => '^8.3', 'laravel/framework' => '^13.0'], 'require-dev' => ['pestphp/pest' => '^5.0']],
        'npm' => ['dependencies' => [], 'devDependencies' => ['vite' => '^8.0']],
    ]);
});

test('route list: lists the project routes from artisan', function (): void {
    Process::fake(['*route:list*' => Process::result(json_encode([
        ['method' => 'GET|HEAD', 'uri' => '/', 'name' => null, 'action' => 'Closure', 'middleware' => ['web']],
        ['method' => 'POST', 'uri' => 'users', 'name' => 'users.store', 'action' => 'App\\Http\\Controllers\\UserController@store', 'middleware' => ['web', 'auth']],
    ]))]);

    $dir = scenarioProject(['artisan' => '<?php']);

    expect((new CodingTools($dir))->routes())->toBe([
        ['method' => 'GET|HEAD', 'uri' => '/', 'name' => null, 'action' => 'Closure', 'middleware' => ['web']],
        ['method' => 'POST', 'uri' => 'users', 'name' => 'users.store', 'action' => 'App\\Http\\Controllers\\UserController@store', 'middleware' => ['web', 'auth']],
    ]);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === 'php artisan route:list --json --except-vendor'
        && $process->path === $dir);
});

test('route list: explains when artisan cannot list the routes', function (): void {
    Process::fake(['*' => Process::result(errorOutput: 'Class "Foo" not found', exitCode: 1)]);

    (new CodingTools(scenarioProject(['artisan' => '<?php'])))->routes();
})->throws(RuntimeException::class, 'Class "Foo" not found');

test('/testing Livewire CRUD for the users model: creates the page component and routes /testing to it', function (): void {
    $dir = scenarioProject(['routes/web.php' => "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n"]);
    $crud = <<<'BLADE'
        <?php

        use App\Models\User;
        use Livewire\Component;

        new class extends Component {
            public function delete(User $user): void
            {
                $user->delete();
            }
        };
        ?>

        <div>
            @foreach (User::all() as $user)
                <div wire:key="{{ $user->id }}">{{ $user->name }} <button wire:click="delete({{ $user->id }})">Delete</button></div>
            @endforeach
        </div>
        BLADE;

    $tools = new CodingTools($dir);
    $component = $tools->createLivewireComponent('pages::users.index', $crud);
    $tools->createRoute('/testing', 'pages::users.index', CodingTools::ROUTE_LIVEWIRE);

    expect($component->path)->toBe('resources/views/pages/users/⚡index.blade.php')
        ->and(File::get($dir.'/resources/views/pages/users/⚡index.blade.php'))->toBe($crud)
        ->and(File::get($dir.'/routes/web.php'))->toContain("Route::livewire('/testing', 'pages::users.index');");
});

test('Livewire component: a plain component starts from the single-file stub, without its notes', function (): void {
    $dir = scenarioProject();

    $change = (new CodingTools($dir))->createLivewireComponent('post.create');

    expect($change->path)->toBe('resources/views/components/post/⚡create.blade.php')
        ->and($change->after)->toStartWith("<?php\n")
        ->and($change->after)->toContain('new class extends Component')
        ->and($change->after)->not->toContain('Source:');
});

test('Livewire component: rejects a name that is not a component name', function (): void {
    (new CodingTools(scenarioProject()))->createLivewireComponent('../evil');
})->throws(InvalidArgumentException::class, 'not a valid Livewire component name');

test('documentation: lists the project docs with their titles', function (): void {
    $tools = new CodingTools(scenarioProject([
        'README.md' => "# Acme Shop\n\nHello.",
        'docs/setup/install.md' => "Intro line\n\n## Installing\n",
        'vendor/pkg/README.md' => '# Not ours',
        'notes.txt' => 'not markdown',
    ]));

    expect($tools->documentation())->toBe([
        ['path' => 'README.md', 'title' => 'Acme Shop'],
        ['path' => 'docs/setup/install.md', 'title' => 'Installing'],
    ]);
});

test('full test suite: runs the tests and reports a pass', function (): void {
    Process::fake(['*' => Process::result("Tests: 12 passed\n")]);

    $dir = scenarioProject();

    expect((new CodingTools($dir))->runCheck('tests'))->toMatchArray([
        'check' => 'tests',
        'command' => 'php artisan test --compact',
        'passed' => true,
        'exit_code' => 0,
        'output' => 'Tests: 12 passed',
        'failures' => [],
    ]);

    Process::assertRan(fn (PendingProcess $process): bool => $process->path === $dir);
});

test('test file for a given class: writes a Pest test with a todo for each public method', function (): void {
    $dir = scenarioProject(['app/Services/InvoiceTotals.php' => <<<'PHP'
        <?php

        namespace App\Services;

        final class InvoiceTotals
        {
            public function __construct(private int $rate) {}

            public function subtotal(array $lines): int { return 0; }

            public static function withTax(int $amount): int { return $amount; }

            private function round(int $amount): int { return $amount; }
        }
        PHP]);

    $change = (new CodingTools($dir))->createTestFile('app/Services/InvoiceTotals.php');

    expect($change->path)->toBe('tests/Feature/InvoiceTotalsTest.php')
        ->and(File::get($dir.'/tests/Feature/InvoiceTotalsTest.php'))->toBe(<<<'PHP'
            <?php

            use App\Services\InvoiceTotals;

            test('it can be resolved from the container', function (): void {
                expect(app(InvoiceTotals::class))->toBeInstanceOf(InvoiceTotals::class);
            });

            test('subtotal')->todo();

            test('withTax')->todo();

            PHP);
});

test('test file for a given class: explains a file with no class', function (): void {
    (new CodingTools(scenarioProject(['app/helpers.php' => "<?php\n\nfunction x() {}\n"])))->createTestFile('app/helpers.php');
})->throws(InvalidArgumentException::class, 'No class found in app/helpers.php');

test('fixing an error from pasted markdown: finds the message and the project lines it points at', function (): void {
    $lines = implode("\n", array_map(fn (int $n): string => "line {$n}", range(1, 20)));
    $dir = scenarioProject(['app/Http/Controllers/UserController.php' => $lines]);

    $pasted = <<<MD
        ## Error

        ```
        Illuminate\\Database\\QueryException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'nmae'
          at {$dir}/app/Http/Controllers/UserController.php:12
          at /somewhere/else/vendor/laravel/Thing.php:40
        ```
        Also see app/Http/Controllers/UserController.php:3 and app/Missing.php:9.
        MD;

    $found = (new CodingTools($dir))->locateError($pasted);

    expect($found['message'])->toBe("Illuminate\\Database\\QueryException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'nmae'")
        ->and($found['locations'])->toHaveCount(2)
        ->and($found['locations'][0])->toBe([
            'path' => 'app/Http/Controllers/UserController.php',
            'line' => 12,
            'excerpt' => "9: line 9\n10: line 10\n11: line 11\n12: line 12\n13: line 13\n14: line 14\n15: line 15",
        ])
        ->and($found['locations'][1]['line'])->toBe(3);
});

test('the do:something {userId} artisan command: is created, and marks that user verified', function (): void {
    $dir = scenarioProject();

    $change = (new CodingTools($dir))->createArtisanCommand(
        signature: 'do:something {userId}',
        description: 'Mark a user as verified',
        handle: <<<'PHP'
            $user = User::find($this->argument('userId'));

            if ($user === null) {
                $this->error('No user with that id.');

                return self::FAILURE;
            }

            $user->forceFill(['email_verified_at' => now()])->save();

            $this->info("User {$user->id} updated.");

            return self::SUCCESS;
            PHP,
        imports: [User::class],
    );

    expect($change->path)->toBe('app/Console/Commands/DoSomething.php')
        ->and($change->after)->toContain("protected \$signature = 'do:something {userId}';")
        ->and($change->after)->toContain("use App\\Models\\User;\nuse Illuminate\\Console\\Command;");

    // Run the generated command here, as the target app would.
    require $dir.'/app/Console/Commands/DoSomething.php';
    app(Kernel::class)->registerCommand(app(DoSomething::class));

    $user = User::factory()->unverified()->create();

    expect(Artisan::call('do:something', ['userId' => $user->id]))->toBe(0)
        ->and(Artisan::output())->toContain("User {$user->id} updated.")
        ->and($user->fresh()->email_verified_at)->not->toBeNull()
        ->and(Artisan::call('do:something', ['userId' => 999]))->toBe(1);
});

test('artisan command: rejects a signature that is not a command signature', function (): void {
    (new CodingTools(scenarioProject()))->createArtisanCommand("x'; evil(); '", 'x', 'return 0;');
})->throws(InvalidArgumentException::class, 'not a valid command signature');

test('running pint, rector, phpstan and tests: each runs its own command in the project', function (string $check, string $command): void {
    Process::fake();

    $result = (new CodingTools(scenarioProject()))->runCheck($check);

    expect($result['command'])->toBe($command)
        ->and($result['passed'])->toBeTrue();

    Process::assertRan($command);
})->with([
    ['pint', 'php vendor/bin/pint'],
    ['rector', 'php vendor/bin/rector process --dry-run'],
    ['phpstan', 'php vendor/bin/phpstan analyse --no-progress'],
    ['tests', 'php artisan test --compact'],
]);

test('running checks: refuses a check that is not configured', function (): void {
    Process::fake();

    (new CodingTools(scenarioProject()))->runCheck('rm -rf /');

    Process::assertNothingRan();
})->throws(InvalidArgumentException::class, 'Check must be one of');

test('fixing their failures: a failing check points at the failing lines', function (): void {
    $dir = scenarioProject(['app/Models/Post.php' => "<?php\n\nclass Post\n{\n}\n"]);

    Process::fake(['*' => Process::result(
        output: " ------ ----------------\n  Line   app/Models/Post.php\n ------ ----------------\n  Error in app/Models/Post.php:4 Access to an undefined property.\n",
        exitCode: 1,
    )]);

    $result = (new CodingTools($dir))->runCheck('phpstan');

    expect($result['passed'])->toBeFalse()
        ->and($result['exit_code'])->toBe(1)
        ->and($result['failures'])->toHaveCount(1)
        ->and($result['failures'][0])->toMatchArray(['path' => 'app/Models/Post.php', 'line' => 4]);
});

test('git diff review: shows the changes on a feature branch since it left main', function (): void {
    $dir = scenarioProject(['app.txt' => "one\n"]);

    git($dir, ['init', '--initial-branch=main']);
    git($dir, ['add', '.']);
    git($dir, ['commit', '-m', 'Start']);
    git($dir, ['checkout', '-b', 'feature-branch']);
    File::put($dir.'/app.txt', "one\ntwo\n");
    git($dir, ['commit', '-am', 'Feature']);
    git($dir, ['checkout', 'main']);
    File::put($dir.'/main-only.txt', "main\n");
    git($dir, ['add', '.']);
    git($dir, ['commit', '-m', 'Main moves on']);

    $diff = (new CodingTools($dir))->gitDiff('main', 'feature-branch');

    expect($diff)->toContain('+two')
        ->and($diff)->not->toContain('main-only.txt');
});

test('git diff review: rejects a ref that could be read as an option', function (): void {
    (new CodingTools(scenarioProject()))->gitDiff('--output=/tmp/x', 'feature');
})->throws(InvalidArgumentException::class, 'not a valid git ref');

test('security review: flags risky code with where it is', function (): void {
    $tools = new CodingTools(scenarioProject([
        'app/Http/Controllers/PostController.php' => "<?php\n\nPost::create(\$request->all());\nDB::select(DB::raw(\"select * from posts where id = \$id\"));\n\$key = env('API_KEY');\n",
        'resources/views/post.blade.php' => "<div>{!! \$post->body !!}</div>\n",
        'config/services.php' => "<?php\n\nreturn ['key' => env('API_KEY')];\n",
        'app/Models/Post.php' => "<?php\n\nclass Post extends Model\n{\n    protected \$guarded = [];\n}\n",
        'vendor/pkg/Bad.php' => "<?php\n\neval(\$x);\n",
    ]));

    expect(collect($tools->securityReview())->map(fn (array $finding): string => $finding['path'].':'.$finding['line'].' '.$finding['rule'])->all())->toBe([
        'app/Http/Controllers/PostController.php:3 Mass assignment from the whole request',
        'app/Http/Controllers/PostController.php:4 Raw SQL built from a variable',
        'app/Http/Controllers/PostController.php:5 env() outside config (null once config is cached)',
        'app/Models/Post.php:5 Every attribute mass assignable',
        'resources/views/post.blade.php:1 Unescaped Blade output',
    ]);
});
