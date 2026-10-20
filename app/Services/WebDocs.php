<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The docs snapshots the agent reads instead of the web. `refresh()` fetches
 * each approved source's pages (see DocSources) into markdown files under
 * `web_docs_path`, one folder per source, each starting with the URL it came
 * from; `search()` and `read()` serve them, and the stubs in `stubs_path`, to
 * the SearchDocs and ReadDoc tools, always with the URL to cite.
 */
class WebDocs
{
    public const string STUBS = 'stubs';

    private const int MAX_REDIRECTS = 3;

    private const array STOP_WORDS = ['a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'do', 'for', 'from', 'how', 'i', 'in', 'is', 'it', 'of', 'on', 'or', 'the', 'to', 'use', 'what', 'with'];

    public function __construct(private readonly DocSources $sources) {}

    /**
     * Fetch every enabled source's pages (or just one source's), writing a
     * snapshot only where the page changed and removing those of pages no
     * longer listed.
     *
     * @return array<string, array{changed: array<int, string>, unchanged: array<int, string>, failed: array<string, string>}>
     */
    public function refresh(?string $key = null): array
    {
        set_time_limit(0);

        $sources = $key === null ? $this->sources->enabled() : array_filter([$key => $this->sources->find($key)]);
        $results = [];

        foreach ($sources as $sourceKey => $source) {
            $results[$sourceKey] = $this->refreshSource($sourceKey, $source);
        }

        return $results;
    }

    /**
     * @param  array{name: string, base_url: string, pages: array<int, string>, enabled: bool}  $source
     * @return array{changed: array<int, string>, unchanged: array<int, string>, failed: array<string, string>}
     */
    private function refreshSource(string $key, array $source): array
    {
        $result = ['changed' => [], 'unchanged' => [], 'failed' => []];
        $host = (string) parse_url($source['base_url'], PHP_URL_HOST);

        foreach ($source['pages'] as $page) {
            try {
                [$url, $body, $contentType] = $this->fetch(DocSources::pageUrl($source, $page), $host);

                $markdown = str_contains($contentType, 'html')
                    ? app(HtmlToMarkdown::class)->convert($body, $url)
                    : trim($body)."\n";

                if (trim($markdown) === '') {
                    throw new RuntimeException('No article text was found on the page.');
                }
            } catch (Throwable $e) {
                $result['failed'][$page] = Str::limit($e->getMessage(), 300);

                continue;
            }

            $existing = $this->snapshot($key, $page);

            if ($existing !== null && $existing['url'] === $url && $existing['content'] === $markdown) {
                $result['unchanged'][] = $page;

                continue;
            }

            $this->writeSnapshot($key, $page, $url, $markdown);
            $result['changed'][] = $page;
        }

        $this->pruneSnapshots($key, $source['pages']);

        $settings = app(AppSettings::class);
        $checked = $settings->get(AppSettings::DOCS_CHECKED_AT, []);
        $settings->set(AppSettings::DOCS_CHECKED_AT, [...(is_array($checked) ? $checked : []), $key => now()->toIso8601String()]);

        return $result;
    }

    /**
     * Fetch a page over https, following redirects only within `$host`.
     *
     * @return array{0: string, 1: string, 2: string} the final URL, the body and its content type
     */
    private function fetch(string $url, string $host): array
    {
        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            $response = $this->http()->get($url);

            if ($response->redirect()) {
                $location = (string) $response->header('Location');
                $next = str_starts_with($location, '/') && ! str_starts_with($location, '//')
                    ? 'https://'.$host.$location
                    : $location;

                if (strtolower((string) parse_url($next, PHP_URL_SCHEME)) !== 'https' || strtolower((string) parse_url($next, PHP_URL_HOST)) !== $host) {
                    throw new RuntimeException("Refused a redirect away from {$host}, to {$next}.");
                }

                $url = $next;

                continue;
            }

            if (! $response->successful()) {
                throw new RuntimeException("{$url} answered HTTP {$response->status()}.");
            }

            $body = $response->body();

            if (strlen($body) > (int) config('synthera-coder.docs_max_page_bytes', 3 * 1024 * 1024)) {
                throw new RuntimeException("{$url} is larger than the page limit.");
            }

            return [$url, $body, strtolower((string) $response->header('Content-Type')) ?: 'text/html'];
        }

        throw new RuntimeException("{$url} redirected too many times.");
    }

    private function http(): PendingRequest
    {
        $caBundle = config('synthera-coder.docs_ca_bundle');

        return Http::withoutRedirecting()
            ->timeout((int) config('synthera-coder.docs_timeout', 30))
            ->withUserAgent('SyntheraCoder-docs-refresh')
            ->accept('text/html, text/markdown;q=0.9, text/plain;q=0.8')
            ->withOptions(['verify' => is_string($caBundle) && $caBundle !== '' ? $caBundle : true]);
    }

    /**
     * When a source was last refreshed, if ever.
     */
    public function checkedAt(string $key): ?Carbon
    {
        $checked = app(AppSettings::class)->get(AppSettings::DOCS_CHECKED_AT, []);

        return is_array($checked) && is_string($checked[$key] ?? null) ? Carbon::parse($checked[$key]) : null;
    }

    /**
     * Whether a source is due a refresh: never checked, or not for a while.
     */
    public function isStale(string $key): bool
    {
        $checkedAt = $this->checkedAt($key);

        return $checkedAt === null || $checkedAt->lt(now()->subDays((int) config('synthera-coder.docs_stale_after_days', 14)));
    }

    /**
     * A page's snapshot, if it has been fetched.
     *
     * @return array{url: string, fetched_at: string, content: string}|null
     */
    public function snapshot(string $key, string $page): ?array
    {
        $file = $this->snapshotFile($key, $page);

        if ($file === null || ! File::exists($file)) {
            return null;
        }

        $raw = str_replace("\r\n", "\n", File::get($file));

        if (! preg_match('/\A---\n(.*?)\n---\n\n?(.*)\z/s', $raw, $parts)) {
            return null;
        }

        $meta = [];

        foreach (explode("\n", $parts[1]) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $meta[trim($name)] = trim($value);
            }
        }

        return [
            'url' => $meta['url'] ?? '',
            'fetched_at' => $meta['fetched_at'] ?? '',
            'content' => $parts[2],
        ];
    }

    private function writeSnapshot(string $key, string $page, string $url, string $markdown): void
    {
        $file = (string) $this->snapshotFile($key, $page);

        File::ensureDirectoryExists(dirname($file));
        File::put($file, implode("\n", [
            '---',
            'source: '.$key,
            'page: '.$page,
            'url: '.$url,
            'fetched_at: '.now()->toIso8601String(),
            '---',
            '',
            $markdown,
        ]));
    }

    /**
     * @param  array<int, string>  $pages
     */
    private function pruneSnapshots(string $key, array $pages): void
    {
        $directory = $this->path().'/'.$key;

        if (! File::isDirectory($directory)) {
            return;
        }

        $keep = array_map(fn (string $page): string => self::slug($page).'.md', $pages);

        foreach (File::files($directory) as $file) {
            if ($file->getExtension() === 'md' && ! in_array($file->getFilename(), $keep, true)) {
                File::delete($file->getPathname());
            }
        }
    }

    private function snapshotFile(string $key, string $page): ?string
    {
        if (! DocSources::isValidKey($key) || ! DocSources::isValidPage($page)) {
            return null;
        }

        return $this->path().'/'.$key.'/'.self::slug($page).'.md';
    }

    private static function slug(string $page): string
    {
        return str_replace('/', '--', $page);
    }

    private function path(): string
    {
        return rtrim((string) config('synthera-coder.web_docs_path'), '/\\');
    }

    /**
     * The stubs: starting files, each citing the docs page it follows.
     *
     * @return array<string, array{file: string, sources: array<int, string>, content: string}>
     */
    public function stubs(): array
    {
        $directory = (string) config('synthera-coder.stubs_path');

        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file): bool => $file->getExtension() === 'stub')
            ->sortBy(fn ($file): string => $file->getFilename())
            ->mapWithKeys(function ($file): array {
                $content = File::get($file->getPathname());
                preg_match_all('#Source:\s*(https://[^\s*]+?)(?=\s|--}}|\*/|$)#m', $content, $sources);

                return [Str::before($file->getFilename(), '.') => [
                    'file' => $file->getFilename(),
                    'sources' => array_values(array_unique($sources[1])),
                    'content' => $content,
                ]];
            })
            ->all();
    }

    /**
     * Every document the agent may read, by id (`source/page`, or `stubs/name`):
     * the fetched pages of enabled sources, then the stubs.
     *
     * @return array<string, array{title: string, url: string, content: string}>
     */
    public function documents(?string $onlySource = null): array
    {
        $documents = [];

        foreach ($this->sources->enabled() as $key => $source) {
            if ($onlySource !== null && $onlySource !== $key) {
                continue;
            }

            foreach ($source['pages'] as $page) {
                $snapshot = $this->snapshot($key, $page);

                if ($snapshot !== null) {
                    $documents[$key.'/'.$page] = [
                        'title' => $source['name'].': '.($this->title($snapshot['content']) ?? $page),
                        'url' => $snapshot['url'],
                        'content' => $snapshot['content'],
                    ];
                }
            }
        }

        if ($onlySource === null || $onlySource === self::STUBS) {
            foreach ($this->stubs() as $name => $stub) {
                $documents[self::STUBS.'/'.$name] = [
                    'title' => 'Stub: '.$stub['file'],
                    'url' => implode(' ', $stub['sources']),
                    'content' => $stub['content'],
                ];
            }
        }

        return $documents;
    }

    /**
     * The sections that best match the words of `$query`, best first.
     *
     * @return array<int, array{id: string, title: string, heading: string, url: string, excerpt: string}>
     */
    public function search(string $query, ?string $onlySource = null, int $limit = 5): array
    {
        $terms = collect(preg_split('/[^\p{L}\p{N}:_-]+/u', mb_strtolower($query)) ?: [])
            ->map(fn (string $term): string => trim($term, '-_:'))
            ->filter(fn (string $term): bool => mb_strlen($term) >= 2 && ! in_array($term, self::STOP_WORDS, true))
            ->unique()
            ->values();

        if ($terms->isEmpty()) {
            return [];
        }

        $needed = max(1, (int) ceil($terms->count() / 2));
        $hits = [];

        foreach ($this->documents($onlySource) as $id => $document) {
            $haystackTitle = mb_strtolower($document['title'].' '.$id);

            foreach ($this->sections($document['content']) as $section) {
                $heading = mb_strtolower($section['heading']);
                $body = mb_strtolower($section['body']);
                $matched = 0;
                $score = 0;

                foreach ($terms as $term) {
                    $inHeading = str_contains($heading, $term);
                    $inTitle = str_contains($haystackTitle, $term);
                    $count = min(5, substr_count($body, $term));

                    if ($inHeading || $inTitle || $count > 0) {
                        $matched++;
                        $score += $count + ($inHeading ? 6 : 0) + ($inTitle ? 3 : 0);
                    }
                }

                if ($matched >= $needed) {
                    $hits[] = [
                        'id' => $id,
                        'title' => $document['title'],
                        'heading' => $section['heading'],
                        'url' => $document['url'],
                        'excerpt' => Str::limit(trim($section['body']), 500),
                        // A stub matching every word is the best start for a new file, so it comes early.
                        'rank' => $matched * 100 + $score + ($matched === $terms->count() && str_starts_with($id, self::STUBS.'/') ? 40 : 0),
                    ];
                }
            }
        }

        return collect($hits)
            ->sortByDesc('rank')
            ->take($limit)
            ->map(fn (array $hit): array => collect($hit)->except('rank')->all())
            ->values()
            ->all();
    }

    /**
     * A document by id, or just its sections whose heading contains `$section`.
     *
     * @return array{title: string, url: string, content: string}|null
     */
    public function read(string $id, ?string $section = null): ?array
    {
        $document = $this->documents()[trim($id, " \t/")] ?? null;

        if ($document === null) {
            return null;
        }

        if ($section !== null && trim($section) !== '') {
            $needle = mb_strtolower(trim($section, " \t#"));

            $content = collect($this->sections($document['content']))
                ->filter(fn (array $part): bool => str_contains(mb_strtolower($part['heading']), $needle))
                ->map(fn (array $part): string => $part['text'])
                ->implode("\n");

            return [...$document, 'content' => $content];
        }

        return $document;
    }

    /**
     * The headings of a document (for asking ReadDoc for one section).
     *
     * @return array<int, string>
     */
    public function headings(string $content): array
    {
        return collect($this->sections($content))
            ->pluck('heading')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Split markdown at its `#`, `##` and `###` headings, ignoring `#` lines
     * inside code blocks.
     *
     * @return array<int, array{heading: string, body: string, text: string}>
     */
    private function sections(string $content): array
    {
        $sections = [];
        $current = ['heading' => '', 'lines' => []];
        $inCode = false;

        foreach (explode("\n", $content) as $line) {
            if (str_starts_with(ltrim($line), '```')) {
                $inCode = ! $inCode;
            }

            if (! $inCode && preg_match('/^(#{1,3})\s+(.+)$/', $line, $match)) {
                $sections[] = $current;
                $current = ['heading' => trim($match[2]), 'lines' => [$line]];

                continue;
            }

            $current['lines'][] = $line;
        }

        $sections[] = $current;

        return collect($sections)
            ->map(fn (array $section): array => [
                'heading' => $section['heading'],
                'body' => implode("\n", array_slice($section['lines'], $section['heading'] === '' ? 0 : 1)),
                'text' => implode("\n", $section['lines']),
            ])
            ->filter(fn (array $section): bool => trim($section['text']) !== '')
            ->values()
            ->all();
    }

    private function title(string $content): ?string
    {
        return preg_match('/^#\s+(.+)$/m', $content, $match) ? trim($match[1]) : null;
    }

    /**
     * Note that a chat's agent read a docs page, so its reply can cite it.
     */
    public function cite(int $chatSessionId, string $url): void
    {
        $context = new ContextService($chatSessionId);
        $cited = $context->get('docs_cited', []);

        foreach (preg_split('/\s+/', trim($url)) ?: [] as $one) {
            if ($one !== '' && ! in_array($one, $cited, true)) {
                $cited[] = $one;
            }
        }

        $context->set('docs_cited', $cited);
    }

    /**
     * The docs pages read in a chat since `forgetCitations()`.
     *
     * @return array<int, string>
     */
    public function citations(int $chatSessionId): array
    {
        $cited = (new ContextService($chatSessionId))->get('docs_cited', []);

        return is_array($cited) ? array_values(array_filter($cited, 'is_string')) : [];
    }

    public function forgetCitations(int $chatSessionId): void
    {
        (new ContextService($chatSessionId))->set('docs_cited', []);
    }
}
