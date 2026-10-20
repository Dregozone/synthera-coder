<?php

use App\Ai\Agents\LocalAgent;
use App\Ai\Tools\ReadDoc;
use App\Ai\Tools\SearchDocs;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\AgentRunner;
use App\Services\DocSources;
use App\Services\HtmlToMarkdown;
use App\Services\WebDocs;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;

/*
 * Each test reads and writes its own snapshots folder and its own docs
 * sources, so nothing here touches app/Ai/WebDocs or reaches the web.
 */
beforeEach(function (): void {
    config([
        'synthera-coder.web_docs_path' => storage_path('framework/testing/web-docs-'.Str::uuid()),
        'synthera-coder.doc_sources' => [
            'livewire' => [
                'name' => 'Livewire 4',
                'base_url' => 'https://livewire.laravel.com/docs/4.x/',
                'pages' => ['components'],
            ],
        ],
    ]);

    Http::preventStrayRequests();
});

afterEach(function (): void {
    File::deleteDirectory(config('synthera-coder.web_docs_path'));
});

function docsPage(string $title, string $body): string
{
    return <<<HTML
        <html><body>
            <nav><a href="/docs/4.x/installation">Installation</a></nav>
            <div class="content">
                <h1>{$title}</h1>
                {$body}
            </div>
            <script>alert('x')</script>
        </body></html>
        HTML;
}

/**
 * Write a snapshot as the refresh would, without fetching it.
 */
function writeDocSnapshot(string $source, string $page, string $url, string $markdown): void
{
    $file = config('synthera-coder.web_docs_path').'/'.$source.'/'.str_replace('/', '--', $page).'.md';

    File::ensureDirectoryExists(dirname($file));
    File::put($file, "---\nsource: {$source}\npage: {$page}\nurl: {$url}\nfetched_at: 2026-10-20T00:00:00+00:00\n---\n\n{$markdown}");
}

test('a docs page is reduced to the markdown of its article', function (): void {
    $markdown = app(HtmlToMarkdown::class)->convert(docsPage('Components', <<<'HTML'
        <p>Run <code>make:livewire</code> to <a href="/docs/4.x/pages">create</a> one.</p>
        <h2>Creating components</h2>
        <pre><code class="language-php">new class extends Component {
            public $title = '';
        };</code></pre>
        <ul><li>One</li><li>Two</li></ul>
        HTML), 'https://livewire.laravel.com/docs/4.x/components');

    expect($markdown)
        ->toStartWith("# Components\n")
        ->toContain('Run `make:livewire` to [create](https://livewire.laravel.com/docs/4.x/pages) one.')
        ->toContain("## Creating components\n\n```php\nnew class extends Component {\n    public \$title = '';\n};\n```")
        ->not->toContain('<pre>')
        ->toContain("- One\n- Two")
        ->not->toContain('Installation')
        ->not->toContain('alert');
});

test('only plain https sites and page paths can be approved', function (string $baseUrl): void {
    app(DocSources::class)->save('bad', 'Bad', $baseUrl, ['page']);
})->throws(InvalidArgumentException::class)->with([
    'http' => 'http://laravel.com/docs/',
    'an ip address' => 'https://127.0.0.1/docs/',
    'localhost' => 'https://localhost/docs/',
    'a herd site' => 'https://synthera-coder.test/',
    'a port' => 'https://laravel.com:8443/docs/',
    'a query' => 'https://laravel.com/docs/?x=1',
    'credentials' => 'https://user:pass@laravel.com/docs/',
    'a parent path' => 'https://laravel.com/docs/../admin/',
]);

test('pages must be plain paths under the base url', function (string $page): void {
    app(DocSources::class)->save('laravel', 'Laravel', 'https://laravel.com/docs/13.x', [$page]);
})->throws(InvalidArgumentException::class, 'Not a page path')->with([
    '../secrets', 'https://evil.example/page', 'a//b', 'page?x=1', '%2e%2e/x',
]);

test('sources saved in the app override and add to config until forgotten', function (): void {
    $sources = app(DocSources::class);

    $sources->save('livewire', 'Livewire', 'https://livewire.laravel.com/docs/4.x', ['components', '/pages/', 'pages', ''], false);
    $sources->save('pest', 'Pest 4', 'https://pestphp.com/docs/', ['writing-tests']);

    expect($sources->find('livewire'))->toBe([
        'name' => 'Livewire',
        'base_url' => 'https://livewire.laravel.com/docs/4.x/',
        'pages' => ['components', 'pages'],
        'enabled' => false,
    ])
        ->and(array_keys($sources->enabled()))->toBe(['pest'])
        ->and($sources->isConfigured('pest'))->toBeFalse()
        ->and($sources->isEdited('pest'))->toBeTrue();

    $sources->forget('livewire');
    $sources->forget('pest');

    expect($sources->find('livewire')['enabled'])->toBeTrue()
        ->and($sources->find('pest'))->toBeNull();
});

test('a refresh snapshots each page with its url, only rewriting what changed', function (): void {
    Http::fake([
        'livewire.laravel.com/docs/4.x/components' => Http::sequence()
            ->push(docsPage('Components', '<p>Version one.</p>'), 200, ['Content-Type' => 'text/html'])
            ->push(docsPage('Components', '<p>Version one.</p>'), 200, ['Content-Type' => 'text/html'])
            ->push(docsPage('Components', '<p>Version two.</p>'), 200, ['Content-Type' => 'text/html']),
    ]);

    $webDocs = app(WebDocs::class);

    expect($webDocs->isStale('livewire'))->toBeTrue()
        ->and($webDocs->refresh()['livewire']['changed'])->toBe(['components']);

    $file = config('synthera-coder.web_docs_path').'/livewire/components.md';

    expect(File::get($file))
        ->toStartWith("---\nsource: livewire\npage: components\nurl: https://livewire.laravel.com/docs/4.x/components\nfetched_at: ")
        ->toContain("# Components\n\nVersion one.")
        ->and($webDocs->isStale('livewire'))->toBeFalse()
        ->and($webDocs->refresh()['livewire']['unchanged'])->toBe(['components'])
        ->and($webDocs->refresh('livewire')['livewire']['changed'])->toBe(['components'])
        ->and($webDocs->snapshot('livewire', 'components')['content'])->toContain('Version two.');

    $this->travel(15)->days();

    expect($webDocs->isStale('livewire'))->toBeTrue();
});

test('a refresh follows redirects on the same site and cites where it landed', function (): void {
    Http::fake([
        'livewire.laravel.com/docs/4.x/components' => Http::response('', 301, ['Location' => '/framework/docs/4.x/components']),
        'livewire.laravel.com/framework/docs/4.x/components' => Http::response(docsPage('Components', '<p>Moved.</p>'), 200, ['Content-Type' => 'text/html; charset=utf-8']),
    ]);

    app(WebDocs::class)->refresh();

    expect(app(WebDocs::class)->snapshot('livewire', 'components')['url'])->toBe('https://livewire.laravel.com/framework/docs/4.x/components');
});

test('a refresh refuses a redirect away from the approved site', function (): void {
    Http::fake([
        'livewire.laravel.com/*' => Http::response('', 302, ['Location' => 'https://evil.example/components']),
    ]);

    $result = app(WebDocs::class)->refresh()['livewire'];

    expect($result['failed']['components'])->toContain('Refused a redirect away from livewire.laravel.com')
        ->and(app(WebDocs::class)->snapshot('livewire', 'components'))->toBeNull();

    Http::assertNotSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'evil.example'));
});

test('a refresh keeps the old snapshot when a page fails, and drops pages no longer listed', function (): void {
    writeDocSnapshot('livewire', 'components', 'https://livewire.laravel.com/docs/4.x/components', "# Components\n\nKept.\n");
    writeDocSnapshot('livewire', 'removed', 'https://livewire.laravel.com/docs/4.x/removed', "# Removed\n");

    Http::fake(['*' => Http::response('Server error', 500)]);

    $result = app(WebDocs::class)->refresh()['livewire'];

    expect($result['failed']['components'])->toContain('HTTP 500')
        ->and(app(WebDocs::class)->snapshot('livewire', 'components')['content'])->toContain('Kept.')
        ->and(File::exists(config('synthera-coder.web_docs_path').'/livewire/removed.md'))->toBeFalse();
});

test('the refresh command reports each source, and fails for an unknown one', function (): void {
    Http::fake(['*' => Http::response(docsPage('Components', '<p>Hello.</p>'), 200, ['Content-Type' => 'text/html'])]);

    $this->artisan('synthera:refresh-docs')
        ->expectsOutputToContain('livewire: 1 changed, 0 unchanged, 0 failed')
        ->assertSuccessful();

    $this->artisan('synthera:refresh-docs', ['source' => 'nope'])->assertFailed();
});

test('the docs are refreshed weekly', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'synthera:refresh-docs'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 3 * * 0');
});

test('search finds sections of snapshots and stubs, and read returns a page or a section with its url', function (): void {
    writeDocSnapshot('livewire', 'components', 'https://livewire.laravel.com/docs/4.x/components', <<<'MD'
        # Components

        Livewire components are PHP classes.

        ## Creating components

        Run `php artisan make:livewire post.create` to make a single-file component.

        ```
        # not a heading
        ```

        ## Rendering components

        Use `<livewire:post.create />`.
        MD);

    $webDocs = app(WebDocs::class);
    $hits = $webDocs->search('create single file component');

    // A stub matching every word comes before the docs pages.
    expect($hits[0]['id'])->toStartWith('stubs/')
        ->and(collect($hits)->firstWhere('id', 'stubs/livewire-sfc'))->toMatchArray([
            'url' => 'https://livewire.laravel.com/docs/4.x/components https://livewire.laravel.com/docs/4.x/forms',
        ])
        ->and(collect($hits)->firstWhere('id', 'livewire/components'))->toMatchArray([
            'title' => 'Livewire 4: Components',
            'heading' => 'Creating components',
            'url' => 'https://livewire.laravel.com/docs/4.x/components',
        ])
        ->and($webDocs->search('livewire', 'nope'))->toBe([])
        ->and($webDocs->search('the a to'))->toBe([])
        ->and($webDocs->headings($webDocs->read('livewire/components')['content']))->toBe(['Components', 'Creating components', 'Rendering components'])
        ->and($webDocs->read('livewire/components', 'render')['content'])->toStartWith('## Rendering components')
        ->and($webDocs->read('livewire/../../.env'))->toBeNull();
});

test('a disabled source is not searched or read', function (): void {
    writeDocSnapshot('livewire', 'components', 'https://livewire.laravel.com/docs/4.x/components', "# Components\n\nLivewire.\n");

    app(DocSources::class)->save('livewire', 'Livewire 4', 'https://livewire.laravel.com/docs/4.x/', ['components'], false);

    expect(app(WebDocs::class)->read('livewire/components'))->toBeNull()
        ->and(app(WebDocs::class)->search('livewire', 'livewire'))->toBe([]);
});

test('the docs tools show where each answer came from and note what was read', function (): void {
    writeDocSnapshot('livewire', 'components', 'https://livewire.laravel.com/docs/4.x/components', "# Components\n\n## Creating components\n\nRun make:livewire.\n");

    $session = ChatSession::create();

    $found = (string) (new SearchDocs($session->id))->handle(new Request(['query' => 'creating components', 'source' => 'livewire']));

    expect($found)->toContain('[livewire/components] Livewire 4: Components › Creating components')
        ->toContain('Source: https://livewire.laravel.com/docs/4.x/components');

    $read = (string) (new ReadDoc($session->id))->handle(new Request(['doc' => 'livewire/components']));

    expect($read)->toStartWith("Source: https://livewire.laravel.com/docs/4.x/components\n\n# Components")
        ->and(app(WebDocs::class)->citations($session->id))->toBe(['https://livewire.laravel.com/docs/4.x/components'])
        ->and((string) (new ReadDoc($session->id))->handle(new Request(['doc' => 'livewire/nope'])))->toContain('No doc with the id livewire/nope')
        ->and((string) (new ReadDoc($session->id))->handle(new Request(['doc' => 'livewire/components', 'section' => 'zzz'])))->toContain('Its sections: Components; Creating components')
        ->and((string) (new SearchDocs($session->id))->handle(new Request(['query' => 'zzzqqq'])))->toContain('No docs matched');
});

test('a reply built on docs the model did not cite gets them listed as sources', function (): void {
    [$session, $userMessage] = (function (): array {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'synthera-'.uniqid();
        File::makeDirectory($dir, 0755, true);

        $session = ChatSession::create([
            'current_working_directory' => $dir,
            'current_model' => 'qwen/qwen3.5-9b',
            'current_chat_type' => 'agent',
            'title' => 'Docs',
        ]);

        return [$session, ChatMessage::create(['chat_session_id' => $session->id, 'type' => 'agent', 'by' => 'user', 'content' => 'Make a component'])];
    })();

    $webDocs = app(WebDocs::class);
    $webDocs->cite($session->id, 'https://example.com/left-over-from-an-earlier-turn');

    LocalAgent::fake([function () use ($session, $webDocs): string {
        // What ReadDoc does when the model reads two pages during the turn.
        $webDocs->cite($session->id, 'https://livewire.laravel.com/docs/4.x/components');
        $webDocs->cite($session->id, 'https://livewire.laravel.com/docs/4.x/pages');

        return 'Use a single-file component. Source: https://livewire.laravel.com/docs/4.x/pages';
    }]);

    app(AgentRunner::class)->runForUserMessage($session->id, $userMessage->id);

    $reply = ChatMessage::query()->where('chat_session_id', $session->id)->where('by', 'assistant')->latest('id')->first();

    expect($reply->content)->toBe("Use a single-file component. Source: https://livewire.laravel.com/docs/4.x/pages\n\n**Sources**\n- https://livewire.laravel.com/docs/4.x/components");

    File::deleteDirectory($session->current_working_directory);
});

test('every shipped stub cites a page of an approved source', function (): void {
    config(['synthera-coder.doc_sources' => (require config_path('synthera-coder.php'))['doc_sources']]);

    $approved = collect(app(DocSources::class)->enabled())
        ->flatMap(fn (array $source): array => array_map(fn (string $page): string => DocSources::pageUrl($source, $page), $source['pages']));

    $stubs = app(WebDocs::class)->stubs();

    expect($stubs)->toHaveKeys(['livewire-sfc', 'livewire-page', 'livewire-test']);

    foreach ($stubs as $name => $stub) {
        expect($stub['sources'])->not->toBeEmpty("{$name} cites no docs page");

        foreach ($stub['sources'] as $url) {
            expect($approved)->toContain($url);
        }
    }
});

test('the setup page lists the docs sources and stubs, and adds, edits and refreshes sources', function (): void {
    Http::fake(['pestphp.com/*' => Http::response(docsPage('Writing Tests', '<p>Use it().</p>'), 200, ['Content-Type' => 'text/html'])]);

    $page = Livewire::test('pages::agent-setup')
        ->assertSee('Livewire 4')
        ->assertSee('https://livewire.laravel.com/docs/4.x/')
        ->assertSee('livewire-sfc.stub')
        ->call('newSource')
        ->set('sourceKey', 'pest')
        ->set('sourceName', 'Pest 4')
        ->set('sourceBaseUrl', 'http://pestphp.com/docs/')
        ->set('sourcePages', "writing-tests\n")
        ->call('saveSource')
        ->assertHasErrors(['sourceBaseUrl'])
        ->set('sourceBaseUrl', 'https://pestphp.com/docs')
        ->set('sourcePages', "writing-tests\n../admin")
        ->call('saveSource')
        ->assertHasErrors(['sourcePages'])
        ->set('sourcePages', "writing-tests\n")
        ->call('saveSource')
        ->assertHasNoErrors()
        ->assertSet('editingSource', 'pest')
        ->assertSee('not fetched')
        ->call('refreshDocs', 'pest')
        ->assertSee('1 / 1');

    expect(app(WebDocs::class)->snapshot('pest', 'writing-tests')['url'])->toBe('https://pestphp.com/docs/writing-tests');

    $page->call('resetSource');

    expect(app(DocSources::class)->find('pest'))->toBeNull();

    $page->call('editSource', 'livewire')
        ->set('sourceEnabled', false)
        ->call('saveSource')
        ->assertSee('Reset to config')
        ->call('resetSource');

    expect(app(DocSources::class)->find('livewire')['enabled'])->toBeTrue();
});
