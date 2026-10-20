<?php

namespace App\Services;

use App\Models\Setting;

/**
 * The app-wide defaults chosen on the setup page: the soul, chat mode and model
 * new chats start with. Each falls back to its value in
 * `config/synthera-coder.php` until one is chosen, or when the chosen one no
 * longer exists (a model removed from config). The soul and the edited
 * profiles are resolved by AgentSetup, which knows which files exist.
 */
class AppSettings
{
    public const string DEFAULT_SOUL = 'default_soul';

    public const string DEFAULT_CHAT_TYPE = 'default_chat_type';

    public const string DEFAULT_CHAT_MODEL = 'default_chat_model';

    // The profiles edited on the setup page, by name; see AgentSetup::profiles().
    public const string AGENT_PROFILES = 'agent_profiles';

    // The docs sources added or edited on the setup page, by key; see DocSources::all().
    public const string DOC_SOURCES = 'doc_sources';

    // When each docs source was last refreshed, by key; see WebDocs::checkedAt().
    public const string DOCS_CHECKED_AT = 'docs_checked_at';

    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::query()->where('key', $key)->first()->value ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();
    }

    public function defaultChatType(): string
    {
        return $this->chosenFrom(self::DEFAULT_CHAT_TYPE, array_keys(config('synthera-coder.chat_types', [])))
            ?? (string) config('synthera-coder.default_chat_type', 'agent');
    }

    public function defaultChatModel(): string
    {
        return $this->chosenFrom(self::DEFAULT_CHAT_MODEL, config('synthera-coder.chat_models', []))
            ?? (string) config('synthera-coder.default_chat_model', '');
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function chosenFrom(string $key, array $allowed): ?string
    {
        $chosen = $this->get($key);

        return is_string($chosen) && in_array($chosen, $allowed, true) ? $chosen : null;
    }
}
