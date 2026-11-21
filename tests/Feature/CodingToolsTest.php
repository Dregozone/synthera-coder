<?php

use App\Services\CodingTools;
use Illuminate\Http\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;

/**
 * Create a temporary target project with the given files.
 *
 * @param  array<string, string>  $files  relative path => contents
 */
function codingProject(array $files = []): string
{
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'synthera-coding-'.uniqid();
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
 * Load a generated routes file into this app's router, as the target app would.
 */
function loadRoutesFile(string $path, string $prefix = ''): void
{
    Route::prefix($prefix)->group($path);

    app('router')->getRoutes()->refreshNameLookups();
}

afterEach(function (): void {
    foreach (temporaryProjects() as $dir) {
        File::deleteDirectory($dir);
    }
});

test('it refuses a project folder that does not exist', function (): void {
    new CodingTools(sys_get_temp_dir().DIRECTORY_SEPARATOR.'synthera-coding-missing');
})->throws(InvalidArgumentException::class, 'Project folder not found');

test('read file returns the contents with the file size and line count', function (): void {
    $tools = new CodingTools(codingProject(['app/Hello.php' => "<?php\n\necho 'hi';\n"]));

    expect($tools->readFile('app/Hello.php'))->toBe([
        'path' => 'app/Hello.php',
        'contents' => "<?php\n\necho 'hi';\n",
        'size' => 18,
        'lines' => 3,
    ]);
});

test('read file counts a last line with no newline and an empty file as no lines', function (): void {
    $tools = new CodingTools(codingProject(['a.txt' => "one\ntwo", 'empty.txt' => '']));

    expect($tools->readFile('a.txt')['lines'])->toBe(2)
        ->and($tools->readFile('empty.txt')['lines'])->toBe(0);
});

test('read file explains a missing file', function (): void {
    (new CodingTools(codingProject()))->readFile('nope.php');
})->throws(InvalidArgumentException::class, 'File not found: nope.php');

test('every file tool refuses a path outside the project', function (string $path): void {
    (new CodingTools(codingProject()))->createFile($path, 'x');
})->with(['../escape.txt', 'app/../../escape.txt'])
    ->throws(InvalidArgumentException::class, 'outside the project');

test('create file writes a new file, making its folders', function (): void {
    $dir = codingProject();

    $change = (new CodingTools($dir))->createFile('app/Support/Greeter.php', "<?php\n");

    expect(File::get($dir.'/app/Support/Greeter.php'))->toBe("<?php\n")
        ->and($change->path)->toBe('app/Support/Greeter.php')
        ->and($change->isNew())->toBeTrue();
});

test('create file will not overwrite a file that exists', function (): void {
    $dir = codingProject(['notes.txt' => 'keep me']);

    expect(fn () => (new CodingTools($dir))->createFile('notes.txt', 'gone'))
        ->toThrow(InvalidArgumentException::class, 'already exists');

    expect(File::get($dir.'/notes.txt'))->toBe('keep me');
});

test('planning a file change leaves the disk alone', function (): void {
    $dir = codingProject();

    $change = (new CodingTools($dir))->planCreateFile('later.txt', 'soon');

    expect($change->after)->toBe('soon')
        ->and($change->before)->toBeNull()
        ->and(File::exists($dir.'/later.txt'))->toBeFalse();
});

test('edit file replaces the one matching passage', function (): void {
    $dir = codingProject(['config/app.php' => "<?php\n\nreturn ['name' => 'Old'];\n"]);

    $change = (new CodingTools($dir))->editFile('config/app.php', "'Old'", "'New'");

    expect(File::get($dir.'/config/app.php'))->toBe("<?php\n\nreturn ['name' => 'New'];\n")
        ->and($change->before)->toBe("<?php\n\nreturn ['name' => 'Old'];\n")
        ->and($change->isNew())->toBeFalse();
});

test('edit file refuses a passage it cannot find or finds more than once', function (string $search, string $message): void {
    $dir = codingProject(['a.txt' => "same\nsame\nonce\n"]);

    expect(fn () => (new CodingTools($dir))->editFile('a.txt', $search, 'x'))
        ->toThrow(InvalidArgumentException::class, $message);

    expect(File::get($dir.'/a.txt'))->toBe("same\nsame\nonce\n");
})->with([
    'missing' => ['absent', 'not found in a.txt'],
    'ambiguous' => ['same', 'appears 2 times in a.txt'],
    'empty' => ['', 'Say which text to replace'],
]);

test('create route appends a working GET closure route to routes/web.php', function (): void {
    $dir = codingProject(['routes/web.php' => "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/', fn () => 'home');\n"]);

    (new CodingTools($dir))->createRoute('/testing', "return 'Testing works';", name: 'testing');

    $contents = File::get($dir.'/routes/web.php');

    expect($contents)->toStartWith("<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/', fn () => 'home');")
        ->and($contents)->toContain("Route::get('/testing', function () {\n    return 'Testing works';\n})->name('testing');");

    loadRoutesFile($dir.'/routes/web.php', 'target');

    $this->get('/target/testing')->assertOk()->assertSee('Testing works');
    expect(route('testing'))->toEndWith('/target/testing');
});

test('create route passes route parameters to the closure', function (): void {
    $dir = codingProject(['routes/web.php' => "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n"]);

    (new CodingTools($dir))->createRoute('/greet/{name}/{title?}', "return trim(\$title.' '.\$name);");

    loadRoutesFile($dir.'/routes/web.php', 'target');

    $this->get('/target/greet/Ada/Dr')->assertSee('Dr Ada');
    $this->get('/target/greet/Ada')->assertSee('Ada');
});

test('create route routes to a Livewire page component', function (): void {
    $dir = codingProject(['routes/web.php' => "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n"]);

    (new CodingTools($dir))->createRoute('/testing', 'pages::users.index', CodingTools::ROUTE_LIVEWIRE);

    expect(File::get($dir.'/routes/web.php'))->toContain("Route::livewire('/testing', 'pages::users.index');");

    loadRoutesFile($dir.'/routes/web.php', 'target');

    expect(app('router')->getRoutes()->match(Request::create('/target/testing'))->uri())->toBe('target/testing');
});

test('create route adds the Route import to a routes file without one, and creates a missing one', function (): void {
    $withoutImport = codingProject(['routes/web.php' => "<?php\n"]);
    $missing = codingProject();

    (new CodingTools($withoutImport))->createRoute('/a', "return 'a';");
    (new CodingTools($missing))->createRoute('/b', "return 'b';");

    expect(File::get($withoutImport.'/routes/web.php'))->toStartWith("<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/a'")
        ->and(File::get($missing.'/routes/web.php'))->toStartWith("<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/b'");
});

test('create route will not add a GET route that already exists', function (): void {
    $dir = codingProject(['routes/web.php' => "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('testing', fn () => 'x');\n"]);

    (new CodingTools($dir))->createRoute('/testing', "return 'again';");
})->throws(InvalidArgumentException::class, 'already has a GET route for /testing');

test('create route rejects input that is not a route', function (array $arguments, string $message): void {
    $dir = codingProject(['routes/web.php' => "<?php\n"]);

    expect(fn () => (new CodingTools($dir))->createRoute(...$arguments))
        ->toThrow(InvalidArgumentException::class, $message);

    expect(File::get($dir.'/routes/web.php'))->toBe("<?php\n");
})->with([
    'uri with a quote' => [['/x\');evil(', "return 'x';"], 'not a valid route path'],
    'unknown type' => [['/x', 'x', 'redirect'], 'Route type must be one of'],
    'livewire name with code' => [['/x', 'pages::x\'); evil(\'', CodingTools::ROUTE_LIVEWIRE], 'not a valid Livewire component name'],
    'controller without a method' => [['/x', 'App\\Http\\Controllers\\UserController', CodingTools::ROUTE_CONTROLLER], 'Controller@method'],
    'middleware with code' => [['/x', "return 'x';", CodingTools::ROUTE_CLOSURE, ["auth'])->evil(['"]], 'not a valid middleware name'],
    'empty closure' => [['/x', '  '], 'Give the closure a body'],
]);

test('create route routes to a controller method', function (): void {
    $dir = codingProject(['routes/web.php' => "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n"]);

    (new CodingTools($dir))->createRoute('/users', 'App\\Http\\Controllers\\UserController@index', CodingTools::ROUTE_CONTROLLER);

    expect(File::get($dir.'/routes/web.php'))->toContain("Route::get('/users', [\\App\\Http\\Controllers\\UserController::class, 'index']);");
});

test('create route joins an existing group with the same middleware and prefix', function (): void {
    $dir = codingProject(['routes/web.php' => <<<'PHP'
        <?php

        use Illuminate\Support\Facades\Route;

        Route::middleware(['verified', 'auth'])->prefix('admin')->group(function () {
            Route::get('/dashboard', function () {
                return 'dash';
            });
        });

        Route::get('/', fn () => 'home');

        PHP]);

    (new CodingTools($dir))->createRoute('/reports', "return 'reports';", middleware: ['auth', 'verified'], prefix: '/admin/');

    expect(File::get($dir.'/routes/web.php'))->toBe(<<<'PHP'
        <?php

        use Illuminate\Support\Facades\Route;

        Route::middleware(['verified', 'auth'])->prefix('admin')->group(function () {
            Route::get('/dashboard', function () {
                return 'dash';
            });

            Route::get('/reports', function () {
                return 'reports';
            });
        });

        Route::get('/', fn () => 'home');

        PHP);
});

test('create route starts a new group under shared middleware and prefix when none matches', function (): void {
    $dir = codingProject(['routes/web.php' => <<<'PHP'
        <?php

        use Illuminate\Support\Facades\Route;

        Route::middleware('auth')->group(function () {
            Route::get('/profile', fn () => 'profile');
        });

        PHP]);

    (new CodingTools($dir))->createRoute('/reports', "return 'reports';", middleware: ['auth'], prefix: 'admin');

    $contents = File::get($dir.'/routes/web.php');

    expect($contents)->toEndWith(<<<'PHP'
        Route::middleware(['auth'])->prefix('admin')->group(function () {
            Route::get('/reports', function () {
                return 'reports';
            });
        });

        PHP);

    loadRoutesFile($dir.'/routes/web.php', 'target');

    // Behind the auth middleware and under the prefix, a guest is sent to log in.
    $this->get('/target/admin/reports')->assertRedirect(route('login'));
    $this->get('/target/reports')->assertNotFound();
});

test('create api route appends a working route to routes/api.php', function (): void {
    $dir = codingProject([
        'bootstrap/app.php' => "<?php\n\n->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php')",
        'routes/api.php' => "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n",
    ]);

    (new CodingTools($dir))->createApiRoute('post', '/status', "return ['ok' => true];");

    expect(File::get($dir.'/routes/api.php'))->toContain("Route::post('/status', function () {")
        ->and(File::exists($dir.'/routes/web.php'))->toBeFalse();

    loadRoutesFile($dir.'/routes/api.php', 'api');

    $this->postJson('/api/status')->assertOk()->assertExactJson(['ok' => true]);
});

test('create api route asks for install:api when the project has no API routes', function (): void {
    $dir = codingProject(['bootstrap/app.php' => "<?php\n\n->withRouting(web: __DIR__.'/../routes/web.php')"]);

    expect(fn () => (new CodingTools($dir))->createApiRoute('get', '/status', "return 'ok';"))
        ->toThrow(InvalidArgumentException::class, 'php artisan install:api');

    expect(File::exists($dir.'/routes/api.php'))->toBeFalse();
});

test('create api route rejects a method it does not support and a Livewire page', function (string $method, string $type, string $message): void {
    $dir = codingProject(['routes/api.php' => "<?php\n"]);

    expect(fn () => (new CodingTools($dir))->createApiRoute($method, '/x', 'pages::x', $type))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    ['options', CodingTools::ROUTE_CLOSURE, 'HTTP method must be one of'],
    ['get', CodingTools::ROUTE_LIVEWIRE, 'API routes cannot render a Livewire page'],
]);

test('commands run in the target project without this app\'s environment, so it reads its own .env', function (): void {
    Process::fake();

    (new CodingTools(codingProject()))->runCheck('tests');

    Process::assertRan(fn (PendingProcess $process): bool => ($process->environment['APP_KEY'] ?? null) === false
        && ($process->environment['DB_DATABASE'] ?? null) === false);
});
