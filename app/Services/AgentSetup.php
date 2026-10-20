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
 * are filled from the variables passed in.
 */
class AgentSetup
{
    public const string SOUL = 'Soul';

    public const string GUIDELINES = 'Guidelines';

    public const string INSTRUCTIONS = 'Instructions';

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
        if (preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1) {
            throw new InvalidArgumentException("Invalid agent setup file name [{$name}].");
        }

        $file = $this->directory($category).DIRECTORY_SEPARATOR.$name.'.md';

        if (! is_file($file)) {
            throw new InvalidArgumentException("Agent setup file [{$category}/{$name}.md] does not exist.");
        }

        return trim(File::get($file));
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
     * `plan`, `title`). A profile without a `soul` key uses the default soul;
     * one with `'soul' => null` has none.
     *
     * @param  array<string, string>  $variables
     */
    public function forProfile(string $profile, array $variables = []): string
    {
        $profiles = config('synthera-coder.agent_profiles', []);

        if (! array_key_exists($profile, $profiles)) {
            throw new InvalidArgumentException("Agent profile [{$profile}] is not configured.");
        }

        $selection = $profiles[$profile];

        if (! array_key_exists('soul', $selection)) {
            $selection['soul'] = config('synthera-coder.default_soul');
        }

        return $this->compose($selection, $variables);
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

    private function directory(string $category): string
    {
        return ($this->path ?? config('synthera-coder.setup_path', app_path('Ai/Setup'))).DIRECTORY_SEPARATOR.$category;
    }
}
