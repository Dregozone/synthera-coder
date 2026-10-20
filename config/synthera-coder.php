<?php

$models = [
    'qwen/qwen3.5-9b' => [
        'provider' => 'lmstudio',
        'model' => 'qwen/qwen3.5-9b',
        'context_window' => 32768,
        'max_steps' => 12,
        'timeout' => 600,
    ],

    // Smaller model that fits fully on GPU — handy for quick testing.
    'qwen3.5-4b' => [
        'provider' => 'lmstudio',
        'model' => 'qwen3.5-4b',
        'context_window' => 32768,
        'max_steps' => 12,
        'timeout' => 600,
    ],
];

$defaultModel = env('LM_STUDIO_MODEL', 'qwen/qwen3.5-9b');

if (! array_key_exists($defaultModel, $models)) {
    $defaultModel = array_key_first($models);
}

return [
    /*
    |--------------------------------------------------------------------------
    | Turn Execution
    |--------------------------------------------------------------------------
    |
    | When true (default), agent turns run inline in the web request so the app
    | works with Laravel Herd out of the box with no separate queue worker. Set
    | SYNTHERA_RUN_TURNS_INLINE=false to instead push turns onto the queue — in
    | that case you must run a worker (e.g. `php artisan queue:work`).
    |
    */
    'run_turns_inline' => (bool) env('SYNTHERA_RUN_TURNS_INLINE', true),

    /*
    |--------------------------------------------------------------------------
    | Session Context Storage
    |--------------------------------------------------------------------------
    |
    | Directory holding each chat session's context file, stored as
    | `{sessions_path}/{session id}/context.json`. Feature tests point this at
    | a temporary directory so they never touch real session context.
    |
    */
    'sessions_path' => env('SYNTHERA_SESSIONS_PATH', app_path('Ai/Sessions')),

    /*
    |--------------------------------------------------------------------------
    | Chat Models
    |--------------------------------------------------------------------------
    |
    | Each entry maps a UI-visible model label (stored per session as
    | `current_model`) to the AI provider, the concrete model identifier sent
    | to that provider, and generation limits. Adding a model is config-only:
    | no agent class is required. The label must match what LM Studio reports.
    |
    */
    'models' => $models,

    // Flat list of labels for the UI model picker.
    'chat_models' => array_keys($models),

    'default_chat_model' => $defaultModel,

    'chat_types' => [
        'ask' => 'question-mark-circle',
        'plan' => 'list-bullet',
        'agent' => 'rocket-launch',
    ],

    'default_chat_type' => 'agent',

    /*
    |--------------------------------------------------------------------------
    | Agent Setup (Soul, Guidelines, Instructions)
    |--------------------------------------------------------------------------
    |
    | System prompts are composed from markdown files in `setup_path`, which
    | holds a `Soul`, `Guidelines` and `Instructions` folder. Each profile
    | names the files (without `.md`) an agent loads, joined in that order;
    | `{{ placeholders }}` in them are filled in at run time. A profile with
    | no `soul` key uses `default_soul`; `'soul' => null` loads none. The
    | chat modes (ask / plan / agent) use the profile of the same name.
    |
    */
    'setup_path' => app_path('Ai/Setup'),

    'default_soul' => env('SYNTHERA_SOUL', 'synthera'),

    'agent_profiles' => [
        'ask' => [
            'guidelines' => ['laravel', 'small-models'],
            'instructions' => ['ask', 'workspace'],
        ],
        'plan' => [
            'guidelines' => ['laravel', 'tool-use', 'small-models'],
            'instructions' => ['plan', 'workspace'],
        ],
        'agent' => [
            'guidelines' => ['laravel', 'tool-use', 'conventions', 'small-models'],
            'instructions' => ['agent', 'workspace'],
        ],
        'condense' => [
            'soul' => null,
            'instructions' => ['condense'],
        ],
        'title' => [
            'soul' => null,
            'instructions' => ['title'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Project Commands
    |--------------------------------------------------------------------------
    |
    | The one-click commands the sidebar offers, by key. Only these can be run
    | from the page: the browser sends a key, never a command line. Each runs
    | in the selected project as a background process (so a long `composer
    | update` is not cut off by the web server), its output shown as it comes.
    |
    */
    'commands' => [
        'composer-audit' => ['group' => 'Composer', 'label' => 'Audit', 'icon' => 'shield-check', 'command' => 'composer audit'],
        'composer-outdated' => ['group' => 'Composer', 'label' => 'Outdated', 'icon' => 'clock', 'command' => 'composer outdated --direct'],
        'composer-update' => ['group' => 'Composer', 'label' => 'Update', 'icon' => 'chevron-double-up', 'command' => 'composer update'],
        'npm-audit' => ['group' => 'npm', 'label' => 'Audit', 'icon' => 'shield-check', 'command' => 'npm audit'],
        'npm-audit-fix' => ['group' => 'npm', 'label' => 'Audit fix', 'icon' => 'wrench', 'command' => 'npm audit fix'],
        'npm-outdated' => ['group' => 'npm', 'label' => 'Outdated', 'icon' => 'clock', 'command' => 'npm outdated'],
        'npm-update' => ['group' => 'npm', 'label' => 'Update', 'icon' => 'chevron-double-up', 'command' => 'npm update'],
        'tests' => ['group' => 'Tests', 'label' => 'Run tests', 'icon' => 'beaker', 'command' => 'php artisan test'],
        'pint' => ['group' => 'Tests', 'label' => 'Pint', 'icon' => 'sparkles', 'command' => 'vendor/bin/pint'],
        'git-status' => ['group' => 'Git', 'label' => 'Status', 'icon' => 'document-magnifying-glass', 'command' => 'git status'],
        'git-log' => ['group' => 'Git', 'label' => 'Log', 'icon' => 'list-bullet', 'command' => 'git log --oneline -15'],
        'git-pull' => ['group' => 'Git', 'label' => 'Pull', 'icon' => 'arrow-down', 'command' => 'git pull --ff-only'],
        'git-push' => ['group' => 'Git', 'label' => 'Push', 'icon' => 'arrow-up', 'command' => 'git push'],
    ],

    // The longest a project command may run (seconds).
    'command_timeout' => (int) env('SYNTHERA_COMMAND_TIMEOUT', 1800),

    // The PHP CLI that runs background commands; found automatically when empty.
    'php_binary' => env('SYNTHERA_PHP_BINARY'),

    /*
    |--------------------------------------------------------------------------
    | Model Loading
    |--------------------------------------------------------------------------
    |
    | Seconds a model may sit idle in LM Studio before it unloads itself (sent
    | as `ttl` with each request; LM Studio loads the model when first asked).
    | Short by default, so the GPU is free again soon after you stop asking.
    | 0 leaves loading and unloading to LM Studio's own settings.
    |
    */
    'model_ttl' => (int) env('SYNTHERA_MODEL_TTL', 300),
];
