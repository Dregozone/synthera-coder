<?php

namespace App\Services;

use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * The coding tasks Synthera Coder can carry out in a target project: reading,
 * creating and editing files, adding routes, Livewire components, artisan
 * commands and tests, describing the project, running its quality checks,
 * reviewing a git diff and its security. The agent's tools are thin wrappers
 * over this class, so every task is tested here, by its inputs and outputs.
 *
 * Every path is resolved inside the project folder (FileService); input that
 * cannot be used throws an InvalidArgumentException whose message says what to
 * change, which the tools hand back to the model. Methods named `plan…` work
 * out a FileChange and leave the disk alone (the agent proposes it for
 * approval); their unprefixed twins apply it.
 */
class CodingTools
{
    public const string ROUTE_CLOSURE = 'closure';

    public const string ROUTE_LIVEWIRE = 'livewire';

    public const string ROUTE_CONTROLLER = 'controller';

    public const array API_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    /**
     * Folders skipped when counting the project or looking through its files.
     */
    private const array SKIPPED_FOLDERS = ['vendor', 'node_modules', '.git'];

    /**
     * Lines shown either side of a line an error points at.
     */
    private const int EXCERPT_RADIUS = 3;

    /**
     * Environment variables a child process must not inherit from this app
     * (on top of those in its .env), so the target project reads its own.
     */
    private const array INHERITED_ENVIRONMENT = [
        'APP_ENV', 'APP_KEY', 'APP_DEBUG', 'APP_URL', 'APP_MAINTENANCE_DRIVER', 'BCRYPT_ROUNDS',
        'BROADCAST_CONNECTION', 'CACHE_STORE', 'DB_CONNECTION', 'DB_DATABASE', 'DB_URL', 'DB_HOST',
        'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD', 'MAIL_MAILER', 'QUEUE_CONNECTION', 'SESSION_DRIVER',
    ];

    /**
     * Risky code the security review flags: a rule name, the pattern for one
     * line, and the files it applies to.
     *
     * @var list<array{rule: string, pattern: string, applies: string}>
     */
    private const array SECURITY_RULES = [
        ['rule' => 'Mass assignment from the whole request', 'pattern' => '/(?:create|update|fill|forceFill|insert)\(\s*(?:\$request->|request\(\)->)all\(\)/', 'applies' => 'php'],
        ['rule' => 'Raw SQL built from a variable', 'pattern' => '/(?:DB::raw|DB::statement|DB::unprepared|whereRaw|selectRaw|orderByRaw|havingRaw)\(\s*(?:"[^"]*\$\w|\'[^\']*\'\s*\.\s*\$)/', 'applies' => 'php'],
        ['rule' => 'env() outside config (null once config is cached)', 'pattern' => '/(?<![\w>:$])env\(/', 'applies' => 'outside-config'],
        ['rule' => 'Every attribute mass assignable', 'pattern' => '/\$guarded\s*=\s*\[\s*\]/', 'applies' => 'php'],
        ['rule' => 'Unescaped Blade output', 'pattern' => '/\{!!/', 'applies' => 'blade'],
        ['rule' => 'Code run from a string or the shell', 'pattern' => '/(?<![\w>:$])(?:eval|shell_exec|passthru|system|exec|proc_open|popen)\s*\(/', 'applies' => 'php'],
        ['rule' => 'Unserialize of possibly untrusted data', 'pattern' => '/(?<![\w>:$])unserialize\s*\(/', 'applies' => 'php'],
    ];

    /**
     * Folders the security review looks through.
     */
    private const array SECURITY_FOLDERS = ['app', 'routes', 'resources/views', 'config', 'database'];

    private readonly string $root;

    public function __construct(string $projectRoot, private readonly FileService $fileService = new FileService)
    {
        if (blank($projectRoot) || ! is_dir($projectRoot)) {
            throw new InvalidArgumentException("Project folder not found: {$projectRoot}");
        }

        $this->root = rtrim($projectRoot, '\\/');
    }

    public function root(): string
    {
        return $this->root;
    }

    // ---------------------------------------------------------------------
    // Files
    // ---------------------------------------------------------------------

    /**
     * A file's contents, with its size in bytes and its number of lines.
     *
     * @return array{path: string, contents: string, size: int, lines: int}
     */
    public function readFile(string $path): array
    {
        $absolutePath = $this->resolve($path);

        if (! is_file($absolutePath)) {
            throw new InvalidArgumentException('File not found: '.$this->display($path));
        }

        $contents = File::get($absolutePath);

        return [
            'path' => $this->display($path),
            'contents' => $contents,
            'size' => strlen($contents),
            'lines' => self::lineCount($contents),
        ];
    }

    public function planCreateFile(string $path, string $contents): FileChange
    {
        $absolutePath = $this->resolve($path);

        if (file_exists($absolutePath)) {
            throw new InvalidArgumentException($this->display($path).' already exists: edit it instead.');
        }

        return new FileChange($this->display($path), $absolutePath, null, $contents);
    }

    public function createFile(string $path, string $contents): FileChange
    {
        return $this->apply($this->planCreateFile($path, $contents));
    }

    /**
     * Write a whole file, new or not.
     */
    public function planWriteFile(string $path, string $contents): FileChange
    {
        $absolutePath = $this->resolve($path);

        return new FileChange($this->display($path), $absolutePath, is_file($absolutePath) ? File::get($absolutePath) : null, $contents);
    }

    /**
     * Replace the one passage of a file that matches `search` exactly.
     */
    public function planEditFile(string $path, string $search, string $replace): FileChange
    {
        if ($search === '') {
            throw new InvalidArgumentException('Say which text to replace.');
        }

        $file = $this->readFile($path);
        $occurrences = substr_count($file['contents'], $search);

        if ($occurrences === 0) {
            throw new InvalidArgumentException("The text to replace was not found in {$file['path']}: read the file and copy it exactly.");
        }

        if ($occurrences > 1) {
            throw new InvalidArgumentException("The text to replace appears {$occurrences} times in {$file['path']}: include more of the lines around it.");
        }

        return new FileChange(
            $file['path'],
            $this->resolve($path),
            $file['contents'],
            Str::replaceFirst($search, $replace, $file['contents']),
        );
    }

    public function editFile(string $path, string $search, string $replace): FileChange
    {
        return $this->apply($this->planEditFile($path, $search, $replace));
    }

    /**
     * Write a planned change to disk.
     */
    public function apply(FileChange $change): FileChange
    {
        File::ensureDirectoryExists(dirname($change->absolutePath));
        File::put($change->absolutePath, $change->after);

        return $change;
    }

    // ---------------------------------------------------------------------
    // Routes
    // ---------------------------------------------------------------------

    /**
     * A GET route in routes/web.php. The action is a closure's body, a Livewire
     * page component (`pages::users.index`) or `Controller@method`. With
     * middleware or a prefix, the route joins the group that has exactly
     * those, or starts one.
     *
     * @param  list<string>  $middleware
     */
    public function planRoute(string $uri, string $action, string $type = self::ROUTE_CLOSURE, array $middleware = [], ?string $prefix = null, ?string $name = null): FileChange
    {
        return $this->planRouteIn('web', 'get', $uri, $action, $type, $middleware, $prefix, $name);
    }

    /**
     * @param  list<string>  $middleware
     */
    public function createRoute(string $uri, string $action, string $type = self::ROUTE_CLOSURE, array $middleware = [], ?string $prefix = null, ?string $name = null): FileChange
    {
        return $this->apply($this->planRoute($uri, $action, $type, $middleware, $prefix, $name));
    }

    /**
     * A route in routes/api.php (which `php artisan install:api` sets up).
     *
     * @param  list<string>  $middleware
     */
    public function planApiRoute(string $method, string $uri, string $action, string $type = self::ROUTE_CLOSURE, array $middleware = [], ?string $prefix = null, ?string $name = null): FileChange
    {
        $method = strtolower(trim($method));

        if (! in_array($method, self::API_METHODS, true)) {
            throw new InvalidArgumentException('HTTP method must be one of: '.implode(', ', self::API_METHODS).'.');
        }

        if ($type === self::ROUTE_LIVEWIRE) {
            throw new InvalidArgumentException('API routes cannot render a Livewire page: use a closure or a controller.');
        }

        $bootstrap = $this->root.'/bootstrap/app.php';

        if (! is_file($this->root.'/routes/api.php') || (is_file($bootstrap) && ! str_contains(File::get($bootstrap), 'api.php'))) {
            throw new InvalidArgumentException('This project has no API routes yet: run `php artisan install:api` first (it creates routes/api.php and registers it).');
        }

        return $this->planRouteIn('api', $method, $uri, $action, $type, $middleware, $prefix, $name);
    }

    /**
     * @param  list<string>  $middleware
     */
    public function createApiRoute(string $method, string $uri, string $action, string $type = self::ROUTE_CLOSURE, array $middleware = [], ?string $prefix = null, ?string $name = null): FileChange
    {
        return $this->apply($this->planApiRoute($method, $uri, $action, $type, $middleware, $prefix, $name));
    }

    /**
     * @param  list<string>  $middleware
     */
    private function planRouteIn(string $file, string $method, string $uri, string $action, string $type, array $middleware, ?string $prefix, ?string $name): FileChange
    {
        $uri = '/'.ltrim(trim($uri), '/');

        if (preg_match('/^\/(?:[A-Za-z0-9\-._~]|\{[A-Za-z_]\w*\??\}|\/)*$/', $uri) !== 1) {
            throw new InvalidArgumentException("{$uri} is not a valid route path: use letters, numbers, - _ . / and {parameters}.");
        }

        $middleware = array_values(array_unique(array_filter(array_map(trim(...), $middleware))));

        foreach ($middleware as $middlewareName) {
            if (preg_match('/^[A-Za-z0-9_\-.:,]+$/', $middlewareName) !== 1) {
                throw new InvalidArgumentException("{$middlewareName} is not a valid middleware name.");
            }
        }

        $prefix = $prefix === null ? null : trim($prefix, " \t\n\r\0\x0B/");
        $prefix = $prefix === '' ? null : $prefix;

        if ($prefix !== null && preg_match('/^[A-Za-z0-9\-._~\/{}]+$/', $prefix) !== 1) {
            throw new InvalidArgumentException("{$prefix} is not a valid route prefix.");
        }

        if ($name !== null && trim($name) !== '' && preg_match('/^[A-Za-z0-9\-._]+$/', trim($name)) !== 1) {
            throw new InvalidArgumentException("{$name} is not a valid route name.");
        }

        $statement = $this->routeStatement($method, $uri, $action, $type, trim((string) $name));

        $relativePath = "routes/{$file}.php";
        $absolutePath = $this->resolve($relativePath);
        $before = is_file($absolutePath) ? File::get($absolutePath) : null;
        $contents = $this->withRouteImport($before ?? "<?php\n");

        if ($prefix === null && $this->hasRoute($contents, $method, $uri)) {
            throw new InvalidArgumentException("{$relativePath} already has a ".strtoupper($method)." route for {$uri}.");
        }

        if ($middleware === [] && $prefix === null) {
            return new FileChange($relativePath, $absolutePath, $before, rtrim($contents)."\n\n".$statement."\n");
        }

        $closingBrace = $this->matchingGroupEnd($contents, $middleware, $prefix);

        if ($closingBrace === null) {
            $group = $this->groupHeader($middleware, $prefix)."\n".self::indent($statement, 4)."\n});";

            return new FileChange($relativePath, $absolutePath, $before, rtrim($contents)."\n\n".$group."\n");
        }

        // Add the route as the group's last, before the line that closes it.
        $lineStart = (int) strrpos(substr($contents, 0, $closingBrace), "\n") + 1;
        $head = rtrim(substr($contents, 0, $lineStart));
        $separator = str_ends_with($head, '{') ? "\n" : "\n\n";

        return new FileChange(
            $relativePath,
            $absolutePath,
            $before,
            $head.$separator.self::indent($statement, 4)."\n".substr($contents, $lineStart),
        );
    }

    private function routeStatement(string $method, string $uri, string $action, string $type, string $name): string
    {
        $statement = match ($type) {
            self::ROUTE_CLOSURE => $this->closureRoute($method, $uri, $action),
            self::ROUTE_LIVEWIRE => $this->livewireRoute($uri, $action),
            self::ROUTE_CONTROLLER => $this->controllerRoute($method, $uri, $action),
            default => throw new InvalidArgumentException('Route type must be one of: '.implode(', ', [self::ROUTE_CLOSURE, self::ROUTE_LIVEWIRE, self::ROUTE_CONTROLLER]).'.'),
        };

        return $statement.($name !== '' ? "->name('{$name}')" : '').';';
    }

    private function closureRoute(string $method, string $uri, string $body): string
    {
        $body = trim($body);

        if ($body === '') {
            throw new InvalidArgumentException('Give the closure a body, e.g. return view(\'welcome\');');
        }

        if (! str_ends_with($body, ';') && ! str_ends_with($body, '}')) {
            $body .= ';';
        }

        preg_match_all('/\{([A-Za-z_]\w*)(\?)?\}/', $uri, $matches, PREG_SET_ORDER);

        $parameters = collect($matches)
            ->map(fn (array $match): string => ($match[2] ?? '') === '?' ? "?string \${$match[1]} = null" : "string \${$match[1]}")
            ->implode(', ');

        return "Route::{$method}('{$uri}', function ({$parameters}) {\n".self::indent($body, 4)."\n})";
    }

    private function livewireRoute(string $uri, string $component): string
    {
        $component = trim($component);
        $this->assertComponentName($component);

        return "Route::livewire('{$uri}', '{$component}')";
    }

    private function controllerRoute(string $method, string $uri, string $action): string
    {
        if (preg_match('/^\\\\?([A-Za-z_]\w*(?:\\\\[A-Za-z_]\w*)*)@([A-Za-z_]\w*)$/', trim($action), $match) !== 1) {
            throw new InvalidArgumentException('Name the controller action as Controller@method, e.g. App\\Http\\Controllers\\UserController@index.');
        }

        return "Route::{$method}('{$uri}', [\\{$match[1]}::class, '{$match[2]}'])";
    }

    private function withRouteImport(string $contents): string
    {
        if (str_contains($contents, 'use Illuminate\\Support\\Facades\\Route;')) {
            return $contents;
        }

        $rest = trim((string) preg_replace('/^\s*<\?php/', '', $contents));

        return "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n".($rest !== '' ? "\n".$rest."\n" : '');
    }

    private function hasRoute(string $contents, string $method, string $uri): bool
    {
        $methods = $method === 'get' ? 'get|livewire|view' : $method;
        $path = preg_quote(ltrim($uri, '/'), '/');

        return preg_match("/Route::(?:{$methods})\(\s*['\"]\/?{$path}['\"]/i", $contents) === 1;
    }

    /**
     * @param  list<string>  $middleware
     */
    private function groupHeader(array $middleware, ?string $prefix): string
    {
        $chain = [];

        if ($middleware !== []) {
            $chain[] = "middleware(['".implode("', '", $middleware)."'])";
        }

        if ($prefix !== null) {
            $chain[] = "prefix('{$prefix}')";
        }

        return 'Route::'.implode('->', $chain).'->group(function () {';
    }

    /**
     * Where the closure of the group with exactly this middleware and prefix
     * (and nothing else in its chain) ends, or null when there is none.
     *
     * @param  list<string>  $middleware
     */
    private function matchingGroupEnd(string $contents, array $middleware, ?string $prefix): ?int
    {
        $middlewarePattern = '/middleware\(\s*(\[[^\]]*\]|\'[^\']*\'|"[^"]*")\s*\)/';
        $prefixPattern = '/prefix\(\s*[\'"]([^\'"]*)[\'"]\s*\)/';

        preg_match_all('/Route::((?:(?!;).)*?)->group\(\s*function\s*\(\s*\)\s*(?:use\s*\([^)]*\)\s*)?\{/s', $contents, $groups, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $wanted = $middleware;
        sort($wanted);

        foreach ($groups as $group) {
            $chain = $group[1][0];

            $remainder = (string) preg_replace([$middlewarePattern, $prefixPattern], '', $chain);

            if (trim(str_replace('->', '', $remainder)) !== '') {
                continue;
            }

            $found = [];

            if (preg_match($middlewarePattern, $chain, $middlewareMatch) === 1) {
                preg_match_all('/[\'"]([^\'"]+)[\'"]/', $middlewareMatch[1], $names);
                $found = array_values(array_unique($names[1]));
                sort($found);
            }

            $foundPrefix = preg_match($prefixPattern, $chain, $prefixMatch) === 1 ? trim($prefixMatch[1], '/') : '';

            if ($found !== $wanted || $foundPrefix !== (string) $prefix) {
                continue;
            }

            $openingBrace = $group[0][1] + strlen($group[0][0]) - 1;

            return self::matchingBrace($contents, $openingBrace);
        }

        return null;
    }

    // ---------------------------------------------------------------------
    // Livewire components, artisan commands and tests
    // ---------------------------------------------------------------------

    /**
     * A Livewire 4 single-file component: `pages::users.index` is a page
     * (resources/views/pages/users/⚡index.blade.php), `post.create` a
     * component (resources/views/components/post/⚡create.blade.php). With no
     * contents it starts from the matching stub.
     */
    public function planLivewireComponent(string $name, ?string $contents = null): FileChange
    {
        $name = trim($name);
        $this->assertComponentName($name);

        [$namespace, $dotted] = str_contains($name, '::') ? explode('::', $name, 2) : ['components', $name];
        $segments = explode('.', $dotted);
        $file = '⚡'.array_pop($segments).'.blade.php';
        $path = implode('/', ['resources/views', $namespace, ...$segments, $file]);

        if ($contents === null || trim($contents) === '') {
            $stub = File::get(config('synthera-coder.stubs_path').'/'.($namespace === 'pages' ? 'livewire-page.stub' : 'livewire-sfc.stub'));
            $contents = ltrim((string) preg_replace('/^\{\{--.*?--\}\}/s', '', $stub));
        }

        return $this->planCreateFile($path, $contents);
    }

    public function createLivewireComponent(string $name, ?string $contents = null): FileChange
    {
        return $this->apply($this->planLivewireComponent($name, $contents));
    }

    /**
     * An artisan command class in app/Console/Commands, named after the
     * command (`do:something` is DoSomething), with `handle` as the body of
     * its handle() method.
     *
     * @param  list<string>  $imports  classes to import, e.g. App\Models\User
     */
    public function planArtisanCommand(string $signature, string $description, string $handle, array $imports = []): FileChange
    {
        $signature = trim((string) preg_replace('/\s+/', ' ', $signature));

        if (preg_match('/^([a-z][a-z0-9\-_]*(?::[a-z0-9\-_]+)*)((?:\s\{[^{}\'"\\\\]+\})*)$/i', $signature, $match) !== 1) {
            throw new InvalidArgumentException("{$signature} is not a valid command signature: use e.g. do:something {userId}.");
        }

        foreach ($imports as $import) {
            if (preg_match('/^[A-Za-z_]\w*(?:\\\\[A-Za-z_]\w*)+$/', ltrim($import, '\\')) !== 1) {
                throw new InvalidArgumentException("{$import} is not a class name to import.");
            }
        }

        $class = Str::studly(str_replace([':', '-', '_'], ' ', $match[1]));

        $uses = collect([...$imports, Command::class])
            ->map(fn (string $import): string => ltrim($import, '\\'))
            ->unique()
            ->sort()
            ->map(fn (string $import): string => "use {$import};")
            ->implode("\n");

        $description = addcslashes(trim($description), "'\\");
        $body = self::indent(trim($handle) !== '' ? trim($handle) : 'return self::SUCCESS;', 8);

        $contents = <<<PHP
            <?php

            namespace App\Console\Commands;

            {$uses}

            class {$class} extends Command
            {
                protected \$signature = '{$signature}';

                protected \$description = '{$description}';

                public function handle(): int
                {
            {$body}
                }
            }

            PHP;

        return $this->planCreateFile("app/Console/Commands/{$class}.php", $contents);
    }

    /**
     * @param  list<string>  $imports
     */
    public function createArtisanCommand(string $signature, string $description, string $handle, array $imports = []): FileChange
    {
        return $this->apply($this->planArtisanCommand($signature, $description, $handle, $imports));
    }

    /**
     * A Pest test file for the class in a file: a test that it resolves, and
     * a todo for each of its public methods, to fill in.
     */
    public function planTestFile(string $classPath): FileChange
    {
        $file = $this->readFile($classPath);

        if (preg_match('/^\s*(?:(?:final|abstract|readonly)\s+)*class\s+(\w+)/m', $file['contents'], $classMatch) !== 1) {
            throw new InvalidArgumentException("No class found in {$file['path']}.");
        }

        $class = $classMatch[1];
        $namespace = preg_match('/^namespace\s+([^;\s]+)\s*;/m', $file['contents'], $namespaceMatch) === 1 ? $namespaceMatch[1] : null;

        preg_match_all('/public\s+(?:static\s+)?function\s+(\w+)\s*\(/', $file['contents'], $methods);

        $todos = collect($methods[1])
            ->reject(fn (string $method): bool => str_starts_with($method, '__'))
            ->unique()
            ->map(fn (string $method): string => "test('{$method}')->todo();")
            ->implode("\n\n");

        $contents = "<?php\n\n"
            .($namespace !== null ? "use {$namespace}\\{$class};\n\n" : '')
            ."test('it can be resolved from the container', function (): void {\n"
            ."    expect(app({$class}::class))->toBeInstanceOf({$class}::class);\n"
            ."});\n"
            .($todos !== '' ? "\n".$todos."\n" : '');

        return $this->planCreateFile("tests/Feature/{$class}Test.php", $contents);
    }

    public function createTestFile(string $classPath): FileChange
    {
        return $this->apply($this->planTestFile($classPath));
    }

    // ---------------------------------------------------------------------
    // About the project
    // ---------------------------------------------------------------------

    /**
     * What the project is: its name and description (composer.json), its
     * Laravel and Livewire versions (composer.lock) and the README's intro.
     *
     * @return array{name: string, path: string, description: string|null, laravel: string|null, livewire: string|null, php: string|null, readme: string|null}
     */
    public function overview(): array
    {
        $composer = $this->json('composer.json');
        $installed = collect($this->json('composer.lock')['packages'] ?? [])
            ->filter(fn (mixed $package): bool => is_array($package) && isset($package['name'], $package['version']))
            ->mapWithKeys(fn (array $package): array => [$package['name'] => (string) $package['version']]);

        return [
            'name' => is_string($composer['name'] ?? null) ? $composer['name'] : basename($this->root),
            'path' => $this->root,
            'description' => is_string($composer['description'] ?? null) ? $composer['description'] : null,
            'laravel' => $installed->get('laravel/framework'),
            'livewire' => $installed->get('livewire/livewire'),
            'php' => is_string($composer['require']['php'] ?? null) ? $composer['require']['php'] : null,
            'readme' => $this->readmeIntro(),
        ];
    }

    /**
     * @return array{composer: array{require: array<string, string>, require-dev: array<string, string>}, npm: array{dependencies: array<string, string>, devDependencies: array<string, string>}}
     */
    public function dependencies(): array
    {
        $composer = $this->json('composer.json');
        $npm = $this->json('package.json');

        return [
            'composer' => [
                'require' => (array) ($composer['require'] ?? []),
                'require-dev' => (array) ($composer['require-dev'] ?? []),
            ],
            'npm' => [
                'dependencies' => (array) ($npm['dependencies'] ?? []),
                'devDependencies' => (array) ($npm['devDependencies'] ?? []),
            ],
        ];
    }

    /**
     * The folder to confirm before changing anything: its name, full path,
     * and how many files it holds and their size (leaving out vendor,
     * node_modules and .git).
     *
     * @return array{name: string, path: string, files: int, bytes: int, size: string}
     */
    public function summary(): array
    {
        $files = 0;
        $bytes = 0;

        foreach ($this->projectFiles() as $file) {
            $files++;
            $bytes += $file->getSize();
        }

        return [
            'name' => basename($this->root),
            'path' => $this->root,
            'files' => $files,
            'bytes' => $bytes,
            'size' => Number::fileSize($bytes, maxPrecision: 1),
        ];
    }

    /**
     * The project's own instructions for coding agents (AGENTS.md), if any.
     */
    public function agentsInstructions(): ?string
    {
        $path = $this->root.'/AGENTS.md';

        if (! is_file($path)) {
            return null;
        }

        $contents = trim(File::get($path));

        return $contents === '' ? null : $contents;
    }

    /**
     * The project's markdown docs (outside vendor, node_modules and hidden
     * folders), each with its first heading as its title.
     *
     * @return list<array{path: string, title: string}>
     */
    public function documentation(): array
    {
        $docs = [];

        foreach ($this->projectFiles() as $file) {
            if (strtolower($file->getExtension()) !== 'md') {
                continue;
            }

            $path = $this->relative($file->getPathname());
            $title = preg_match('/^#{1,6}\s+(.+)$/m', (string) file_get_contents($file->getPathname()), $heading) === 1
                ? trim($heading[1])
                : $file->getBasename('.md');

            $docs[$path] = ['path' => $path, 'title' => $title];
        }

        ksort($docs, SORT_STRING);

        return array_values($docs);
    }

    /**
     * The project's routes, from `php artisan route:list`.
     *
     * @return list<array{method: string, uri: string, name: string|null, action: string, middleware: list<string>}>
     */
    public function routes(): array
    {
        if (! is_file($this->root.'/artisan')) {
            throw new InvalidArgumentException('This is not a Laravel project (there is no artisan file).');
        }

        $result = $this->run('php artisan route:list --json --except-vendor');

        if (! $result->successful()) {
            throw new RuntimeException('Could not list the routes: '.trim($result->errorOutput() ?: $result->output()));
        }

        return collect(json_decode($result->output(), true) ?: [])
            ->map(fn (array $route): array => [
                'method' => (string) ($route['method'] ?? ''),
                'uri' => (string) ($route['uri'] ?? ''),
                'name' => $route['name'] ?? null,
                'action' => (string) ($route['action'] ?? ''),
                'middleware' => array_values((array) ($route['middleware'] ?? [])),
            ])
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------------
    // Checks, errors, diffs and security
    // ---------------------------------------------------------------------

    /**
     * The command a quality check runs (config('synthera-coder.quality_checks')).
     */
    public function checkCommand(string $check): string
    {
        $checks = (array) config('synthera-coder.quality_checks', []);

        if (! is_string($checks[$check] ?? null)) {
            throw new InvalidArgumentException('Check must be one of: '.implode(', ', array_keys($checks)).'.');
        }

        return $checks[$check];
    }

    /**
     * Run a quality check (pint, rector, phpstan or tests) in the project. A
     * failing check lists the project lines its output points at.
     *
     * @return array{check: string, command: string, passed: bool, exit_code: int|null, output: string, failures: list<array{path: string, line: int, excerpt: string}>}
     */
    public function runCheck(string $check): array
    {
        $command = $this->checkCommand($check);
        $result = $this->run($command, (int) config('synthera-coder.command_timeout', 1800));

        $output = trim(self::withoutAnsi(trim($result->output())."\n".trim($result->errorOutput())));

        return [
            'check' => $check,
            'command' => $command,
            'passed' => $result->successful(),
            'exit_code' => $result->exitCode(),
            'output' => $output,
            'failures' => $result->successful() ? [] : $this->locateError($output)['locations'],
        ];
    }

    /**
     * Read an error (pasted markdown, a stack trace or a check's output): its
     * message, and each line in the project it points at, with the code
     * around it.
     *
     * @return array{message: string, locations: list<array{path: string, line: int, excerpt: string}>}
     */
    public function locateError(string $text): array
    {
        $lines = collect(preg_split('/\R/', $text) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->reject(fn (string $line): bool => $line === '' || str_starts_with($line, '#') || str_starts_with($line, '```'));

        $message = $lines->first(fn (string $line): bool => preg_match('/^[A-Z][\w\\\\]*(?:Exception|Error)\b/', $line) === 1)
            ?? $lines->first()
            ?? '';

        preg_match_all(
            '/((?:[A-Za-z]:)?[^\s:\'"()`,]+\.(?:php|js|ts|vue))(?::(\d+)| on line (\d+))/',
            $text,
            $matches,
            PREG_SET_ORDER,
        );

        $locations = [];

        foreach ($matches as $match) {
            $line = (int) ($match[2] !== '' ? $match[2] : ($match[3] ?? 0));
            $absolutePath = $this->fileService->resolveFilePath($match[1], $this->root);

            if ($line < 1 || $absolutePath === null || ! is_file($absolutePath)) {
                continue;
            }

            $path = $this->relative($absolutePath);
            $locations[$path.':'.$line] ??= [
                'path' => $path,
                'line' => $line,
                'excerpt' => self::excerpt(File::get($absolutePath), $line),
            ];
        }

        return ['message' => $message, 'locations' => array_values($locations)];
    }

    /**
     * What a branch changes since it left the base branch (`git diff
     * main...feature-branch`). The base defaults to main (or master), the
     * branch to the one checked out.
     */
    public function gitDiff(?string $base = null, ?string $branch = null): string
    {
        $branch = filled($branch) ? trim((string) $branch) : 'HEAD';
        $base = filled($base) ? trim((string) $base) : $this->defaultBranch();

        foreach ([$base, $branch] as $ref) {
            if (preg_match('/^(?!-)[A-Za-z0-9._\-\/~^@]+$/', $ref) !== 1) {
                throw new InvalidArgumentException("{$ref} is not a valid git ref.");
            }
        }

        $result = Process::path($this->root)->run(['git', 'diff', '--no-color', "{$base}...{$branch}"]);

        if (! $result->successful()) {
            throw new RuntimeException('git diff failed: '.trim($result->errorOutput()));
        }

        return $result->output();
    }

    /**
     * Risky code in the project's app, routes, views, config and database
     * folders, by file and line: a starting point for a security review.
     *
     * @return list<array{path: string, line: int, rule: string, code: string}>
     */
    public function securityReview(): array
    {
        $findings = [];

        foreach (self::SECURITY_FOLDERS as $folder) {
            $directory = $this->root.'/'.$folder;

            if (! is_dir($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                if (strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $path = $this->relative($file->getPathname());
                $isBlade = str_ends_with($path, '.blade.php');
                $inConfig = str_starts_with($path, 'config/');

                foreach (preg_split('/\R/', File::get($file->getPathname())) ?: [] as $index => $code) {
                    foreach (self::SECURITY_RULES as $rule) {
                        $applies = match ($rule['applies']) {
                            'blade' => $isBlade,
                            'outside-config' => ! $inConfig,
                            default => true,
                        };

                        if ($applies && preg_match($rule['pattern'], $code) === 1) {
                            $findings[] = ['path' => $path, 'line' => $index + 1, 'rule' => $rule['rule'], 'code' => trim($code)];
                        }
                    }
                }
            }
        }

        usort($findings, fn (array $a, array $b): int => [$a['path'], $a['line']] <=> [$b['path'], $b['line']]);

        return $findings;
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Unset this app's environment for a child process, so the target project
     * reads its own .env rather than inheriting Synthera Coder's (its
     * database, its APP_KEY).
     *
     * @return array<string, false>
     */
    public static function isolatedEnvironment(): array
    {
        $keys = self::INHERITED_ENVIRONMENT;
        $envFile = base_path('.env');

        if (is_file($envFile)) {
            $keys = [...$keys, ...array_keys(Dotenv::parse((string) file_get_contents($envFile)))];
        }

        return array_fill_keys(array_unique($keys), false);
    }

    public static function lineCount(string $contents): int
    {
        if ($contents === '') {
            return 0;
        }

        return substr_count($contents, "\n") + (str_ends_with($contents, "\n") ? 0 : 1);
    }

    private function run(string $command, int $timeout = 120): ProcessResult
    {
        return Process::path($this->root)
            ->env(self::isolatedEnvironment())
            ->timeout($timeout)
            ->run($command);
    }

    private function defaultBranch(): string
    {
        $hasMain = Process::path($this->root)->run(['git', 'rev-parse', '--verify', '--quiet', 'main'])->successful();

        return $hasMain ? 'main' : 'master';
    }

    private function resolve(string $path): string
    {
        if (trim($path) === '') {
            throw new InvalidArgumentException('Give a file path, relative to the project folder.');
        }

        return $this->fileService->resolveFilePath($path, $this->root)
            ?? throw new InvalidArgumentException("{$path} is outside the project folder.");
    }

    /**
     * A path as the project knows it: relative, with forward slashes.
     */
    private function display(string $path): string
    {
        return $this->relative($this->resolve($path));
    }

    private function relative(string $absolutePath): string
    {
        $path = str_replace('\\', '/', $absolutePath);

        foreach (array_unique([$this->root, realpath($this->root) ?: $this->root]) as $root) {
            $root = rtrim(str_replace('\\', '/', $root), '/').'/';

            if (stripos($path, $root) === 0) {
                return substr($path, strlen($root));
            }
        }

        return $path;
    }

    /**
     * Every file in the project, leaving out vendor, node_modules and hidden folders.
     *
     * @return iterable<SplFileInfo>
     */
    private function projectFiles(): iterable
    {
        $directories = new \RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($this->root, RecursiveDirectoryIterator::SKIP_DOTS),
            fn (SplFileInfo $entry): bool => ! $entry->isDir()
                || (! in_array($entry->getFilename(), self::SKIPPED_FOLDERS, true) && ! str_starts_with($entry->getFilename(), '.')),
        );

        foreach (new RecursiveIteratorIterator($directories) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                yield $file;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $path): array
    {
        $absolutePath = $this->root.'/'.$path;

        if (! is_file($absolutePath)) {
            return [];
        }

        $decoded = json_decode(File::get($absolutePath), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function readmeIntro(): ?string
    {
        $path = $this->root.'/README.md';

        if (! is_file($path)) {
            return null;
        }

        $paragraph = collect(preg_split('/\R\s*\R/', File::get($path)) ?: [])
            ->map(fn (string $block): string => trim($block))
            ->first(fn (string $block): bool => $block !== '' && preg_match('/^(?:#|\[!\[|!\[|<|>|---|```|\|)/', $block) !== 1);

        return $paragraph === null ? null : Str::limit((string) preg_replace('/\s+/', ' ', $paragraph), 500);
    }

    private function assertComponentName(string $name): void
    {
        if (preg_match('/^(?:[a-z0-9\-_]+::)?[a-z0-9\-_]+(?:\.[a-z0-9\-_]+)*$/', $name) !== 1) {
            throw new InvalidArgumentException("{$name} is not a valid Livewire component name: use e.g. pages::users.index or post.create.");
        }
    }

    private static function indent(string $code, int $spaces): string
    {
        $padding = str_repeat(' ', $spaces);

        return collect(preg_split('/\R/', $code) ?: [])
            ->map(fn (string $line): string => $line === '' ? '' : $padding.$line)
            ->implode("\n");
    }

    /**
     * The offset of the brace that closes the one at `$openingBrace`, found by
     * PHP's own tokenizer so braces in strings and comments are not counted.
     */
    private static function matchingBrace(string $code, int $openingBrace): ?int
    {
        $offset = 0;
        $depth = 0;

        foreach (token_get_all($code) as $token) {
            $text = is_array($token) ? $token[1] : $token;

            if ($offset >= $openingBrace) {
                if ($text === '{' || $text === '${') {
                    $depth++;
                } elseif ($text === '}') {
                    $depth--;

                    if ($depth === 0) {
                        return $offset;
                    }
                }
            }

            $offset += strlen($text);
        }

        return null;
    }

    private static function excerpt(string $contents, int $line): string
    {
        $lines = preg_split('/\R/', $contents) ?: [];
        $first = max(1, $line - self::EXCERPT_RADIUS);
        $last = min(count($lines), $line + self::EXCERPT_RADIUS);

        return collect(range($first, $last))
            ->map(fn (int $number): string => $number.': '.$lines[$number - 1])
            ->implode("\n");
    }

    private static function withoutAnsi(string $text): string
    {
        return (string) preg_replace('/\e\[[0-9;]*m/', '', $text);
    }
}
