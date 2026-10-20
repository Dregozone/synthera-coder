<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * The approved docs sites the refresh may fetch from: `doc_sources` in
 * `config/synthera-coder.php`, with the sources added or edited on the setup
 * page stored in the database over them. Only https base URLs and plain page
 * paths under them are accepted, so a source can never point the refresh at
 * another site, a local address scheme or a parent path.
 */
class DocSources
{
    /**
     * Every source, by key: config's, with the setup page's edits and additions.
     *
     * @return array<string, array{name: string, base_url: string, pages: array<int, string>, enabled: bool}>
     */
    public function all(): array
    {
        $sources = [];

        foreach ([...$this->configured(), ...$this->edited()] as $key => $source) {
            if (! is_array($source) || ! self::isValidKey((string) $key)) {
                continue;
            }

            try {
                $sources[$key] = $this->normalise($source);
            } catch (InvalidArgumentException) {
                // A source that is no longer valid is left out rather than fetched.
            }
        }

        return $sources;
    }

    /**
     * The sources the refresh fetches and the agent reads.
     *
     * @return array<string, array{name: string, base_url: string, pages: array<int, string>, enabled: bool}>
     */
    public function enabled(): array
    {
        return array_filter($this->all(), fn (array $source): bool => $source['enabled']);
    }

    /**
     * @return array{name: string, base_url: string, pages: array<int, string>, enabled: bool}|null
     */
    public function find(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function isConfigured(string $key): bool
    {
        return array_key_exists($key, $this->configured());
    }

    public function isEdited(string $key): bool
    {
        return array_key_exists($key, $this->edited());
    }

    /**
     * Add or change a source, stored over config's. Throws when the key, base
     * URL or a page is not acceptable.
     *
     * @param  array<int, string>  $pages
     */
    public function save(string $key, string $name, string $baseUrl, array $pages, bool $enabled = true): void
    {
        if (! self::isValidKey($key)) {
            throw new InvalidArgumentException('A source key uses only lowercase letters, numbers and dashes.');
        }

        $source = $this->normalise([
            'name' => $name,
            'base_url' => $baseUrl,
            'pages' => $pages,
            'enabled' => $enabled,
        ]);

        $settings = app(AppSettings::class);
        $settings->set(AppSettings::DOC_SOURCES, [...$this->edited(), $key => $source]);
    }

    /**
     * Drop the setup page's edit: a configured source goes back to config, an
     * added one is removed.
     */
    public function forget(string $key): void
    {
        $edited = $this->edited();
        unset($edited[$key]);

        $settings = app(AppSettings::class);

        $edited === []
            ? $settings->forget(AppSettings::DOC_SOURCES)
            : $settings->set(AppSettings::DOC_SOURCES, $edited);
    }

    /**
     * The URL of a source's page.
     *
     * @param  array{base_url: string}  $source
     */
    public static function pageUrl(array $source, string $page): string
    {
        return $source['base_url'].ltrim($page, '/');
    }

    public static function isValidKey(string $key): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $key);
    }

    /**
     * A plain path under the base URL: no scheme, query, parent or empty segments.
     */
    public static function isValidPage(string $page): bool
    {
        return (bool) preg_match('#^[A-Za-z0-9][A-Za-z0-9._-]*(/[A-Za-z0-9][A-Za-z0-9._-]*)*$#', $page)
            && ! str_contains($page, '..');
    }

    /**
     * An https URL with a host and path only, ending in `/`.
     */
    public static function normaliseBaseUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);

        if ($parts === false
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || ! self::isPublicHostName($parts['host'] ?? '')
            || isset($parts['user'])
            || isset($parts['port'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || str_contains($parts['path'] ?? '', '..')) {
            throw new InvalidArgumentException('The base URL must be a plain https:// address, such as https://laravel.com/docs/13.x/.');
        }

        return 'https://'.strtolower($parts['host']).'/'.ltrim(rtrim($parts['path'] ?? '', '/').'/', '/');
    }

    /**
     * A domain name such as laravel.com: not an IP address, `localhost` or a
     * bare machine name, so a source cannot reach this machine or its network.
     */
    public static function isPublicHostName(string $host): bool
    {
        $host = strtolower($host);

        return filter_var($host, FILTER_VALIDATE_IP) === false
            && ! str_starts_with($host, '[')
            && (bool) preg_match('/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $host)
            && ! preg_match('/(^|\.)(localhost|local|test|internal|lan|home\.arpa)$/', $host);
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array{name: string, base_url: string, pages: array<int, string>, enabled: bool}
     */
    private function normalise(array $source): array
    {
        $pages = collect($source['pages'] ?? [])
            ->map(fn (mixed $page): string => trim((string) $page, " \t/"))
            ->filter(fn (string $page): bool => $page !== '')
            ->unique()
            ->values();

        $invalid = $pages->reject(fn (string $page): bool => self::isValidPage($page));

        if ($invalid->isNotEmpty()) {
            throw new InvalidArgumentException('Not a page path: '.$invalid->implode(', ').'. Give paths under the base URL, such as components or docs/installation.');
        }

        $baseUrl = self::normaliseBaseUrl((string) ($source['base_url'] ?? ''));

        return [
            'name' => trim((string) ($source['name'] ?? '')) ?: (string) parse_url($baseUrl, PHP_URL_HOST),
            'base_url' => $baseUrl,
            'pages' => $pages->all(),
            'enabled' => (bool) ($source['enabled'] ?? true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function configured(): array
    {
        return config('synthera-coder.doc_sources', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function edited(): array
    {
        $edited = app(AppSettings::class)->get(AppSettings::DOC_SOURCES, []);

        return is_array($edited) ? $edited : [];
    }
}
