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
];
