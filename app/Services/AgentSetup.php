<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Builds agent system prompts from the markdown files in `app/Ai/Setup`.
 *
 * The setup directory has one folder per category — `Soul` (persona),
 * `Guidelines` (standing rules) and `Instructions` (the task at hand) — each
 * holding any number of markdown files. A profile in
 * `config/synthera-coder.php` picks which files an agent loads; they are
 * joined in the order soul, guidelines, instructions, and `{{ placeholders }}`
 * are filled from the variables passed in. A chat can override the soul and
 * guidelines its mode's profile would load (chosen in the UI), and the files
 * themselves can be written and deleted from the setup page.
 */
class AgentSetup
{
    public const string SOUL = 'Soul';

    public const string GUIDELINES = 'Guidelines';

    public const string INSTRUCTIONS = 'Instructions';

    /** @var array<int, string> */
    public const array CATEGORIES = [self::SOUL, self::GUIDELINES, self::INSTRUCTIONS];

    /**
     * A chat's soul override meaning "no soul at all" (not a valid file name).
     */
    public const string NO_SOUL = ':none';

    public function __construct(private ?string $path = null) {}

    /**
     * The names (file names without `.md`) of the files available in a category.
     *
     * @return array<int, string>
     */
    public function options(string $category): array
    {
        $directory = $this->directory($category);

        if (! is_dir($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file): bool => $file->getExtension() === 'md')
            ->map(fn ($file): string => $file->getFilenameWithoutExtension())
            ->sort()
            ->values()
            ->all();
    }

    /**
     * The contents of one markdown file, trimmed.
     */
    public function load(string $category, string $name): string
    {
        $file = $this->file($category, $name);

        if (! is_file($file)) {
            throw new InvalidArgumentException("Agent setup file [{$category}/{$name}.md] does not exist.");
        }

        return trim(File::get($file));
    }

    public function exists(string $category, string $name): bool
    {
        return in_array($category, self::CATEGORIES, true)
            && self::isValidName($name)
            && is_file($this->file($category, $name));
    }

    /**
     * A one-line description of a file for pickers: its first line of text
     * that is not a heading, without a list marker.
     */
    public function summary(string $category, string $name): string
    {
        $line = collect(preg_split('/\R/', $this->load($category, $name)) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->first(fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#'), '');

        return ltrim($line, '-* ');
    }

    /**
     * Create or replace a markdown file.
     */
    public function save(string $category, string $name, string $content): void
    {
        $file = $this->file($category, $name);

        File::ensureDirectoryExists(dirname($file));
        File::put($file, rtrim($content).PHP_EOL);
    }

    /**
     * Delete a markdown file. Refuses one a profile or the default soul needs,
     * since loading those would then fail.
     */
    public function delete(string $category, string $name): void
    {
        if ($this->usedBy($category, $name) !== []) {
            throw new InvalidArgumentException("Agent setup file [{$category}/{$name}.md] is in use.");
        }

        File::delete($this->file($category, $name));
    }

    /**
     * What needs this file: the profiles that load it, and the default soul.
     * Chats that picked it are not counted: they fall back when it is gone.
     *
     * @return array<int, string>
     */
    public function usedBy(string $category, string $name): array
    {
        $uses = [];

        if ($category === self::SOUL && $this->defaultSoul() === $name) {
            $uses[] = 'the default soul';
        }

        $key = match ($category) {
            self::SOUL => 'soul',
            self::GUIDELINES => 'guidelines',
            default => 'instructions',
        };

        foreach (config('synthera-coder.agent_profiles', []) as $profile => $selection) {
            if (in_array($name, (array) ($selection[$key] ?? []), true)) {
                $uses[] = "the {$profile} profile";
            }
        }

        return $uses;
    }

    public static function isValidName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9_-]+$/', $name) === 1;
    }

    /**
     * The soul every chat uses unless it picks its own: the one chosen on the
     * setup page while that file exists, otherwise `default_soul` in config.
     */
    public function defaultSoul(): ?string
    {
        $chosen = app(AppSettings::class)->get(AppSettings::DEFAULT_SOUL);

        return is_string($chosen) && $this->exists(self::SOUL, $chosen)
            ? $chosen
            : config('synthera-coder.default_soul');
    }

    /**
     * The guidelines a configured profile loads.
     *
     * @return array<int, string>
     */
    public function profileGuidelines(string $profile): array
    {
        return array_values(config("synthera-coder.agent_profiles.{$profile}.guidelines", []));
    }

    /**
     * Compose a system prompt from an explicit selection of files.
     *
     * @param  array{soul?: string|null, guidelines?: array<int, string>, instructions?: array<int, string>}  $selection
     * @param  array<string, string>  $variables
     */
    public function compose(array $selection, array $variables = []): string
    {
        $sections = [];

        if (filled($selection['soul'] ?? null)) {
            $sections[] = $this->load(self::SOUL, $selection['soul']);
        }

        foreach ($selection['guidelines'] ?? [] as $name) {
            $sections[] = $this->load(self::GUIDELINES, $name);
        }

        foreach ($selection['instructions'] ?? [] as $name) {
            $sections[] = $this->load(self::INSTRUCTIONS, $name);
        }

        return $this->fill(implode(PHP_EOL.PHP_EOL, $sections), $variables);
    }

    /**
     * Compose the system prompt for a configured profile (e.g. `agent`,
     * `plan`, `title`), after a chat's overrides (see selection()).
     *
     * @param  array<string, string>  $variables
     * @param  array{soul?: string|null, guidelines?: array<int, string>|null}  $overrides
     */
    public function forProfile(string $profile, array $variables = [], array $overrides = []): string
    {
        return $this->compose($this->selection($profile, $overrides), $variables);
    }

    /**
     * The files a profile loads. A profile without a `soul` key uses the
     * default soul; one with `'soul' => null` has none. Then a chat's
     * overrides: a `soul` (a soul's name, or NO_SOUL) replaces the profile's
     * soul, and `guidelines` replace its guidelines. Overrides naming files
     * that no longer exist are ignored, so deleting a file never breaks a chat.
     *
     * @param  array{soul?: string|null, guidelines?: array<int, string>|null}  $overrides
     * @return array{soul: string|null, guidelines: array<int, string>, instructions: array<int, string>}
     */
    public function selection(string $profile, array $overrides = []): array
    {
        $profiles = config('synthera-coder.agent_profiles', []);

        if (! array_key_exists($profile, $profiles)) {
            throw new InvalidArgumentException("Agent profile [{$profile}] is not configured.");
        }

        $selection = $profiles[$profile];

        $soul = array_key_exists('soul', $selection) ? $selection['soul'] : $this->defaultSoul();
        $guidelines = $selection['guidelines'] ?? [];

        $soulOverride = $overrides['soul'] ?? null;

        if ($soulOverride === self::NO_SOUL) {
            $soul = null;
        } elseif (is_string($soulOverride) && $this->exists(self::SOUL, $soulOverride)) {
            $soul = $soulOverride;
        }

        if (is_array($overrides['guidelines'] ?? null)) {
            $guidelines = array_filter(
                $overrides['guidelines'],
                fn (string $name): bool => $this->exists(self::GUIDELINES, $name),
            );
        }

        return [
            'soul' => $soul,
            'guidelines' => array_values($guidelines),
            'instructions' => array_values($selection['instructions'] ?? []),
        ];
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function fill(string $prompt, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/',
            fn (array $match): string => $variables[$match[1]] ?? $match[0],
            $prompt,
        );
    }

    private function file(string $category, string $name): string
    {
        if (! in_array($category, self::CATEGORIES, true)) {
            throw new InvalidArgumentException("Unknown agent setup category [{$category}].");
        }

        if (! self::isValidName($name)) {
            throw new InvalidArgumentException("Invalid agent setup file name [{$name}].");
        }

        return $this->directory($category).DIRECTORY_SEPARATOR.$name.'.md';
    }

    private function directory(string $category): string
    {
        return ($this->path ?? config('synthera-coder.setup_path', app_path('Ai/Setup'))).DIRECTORY_SEPARATOR.$category;
    }
}
